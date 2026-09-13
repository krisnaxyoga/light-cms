<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\Media\ImageProcessor;
use App\Models\ActivityLogModel;
use App\Models\MediaModel;
use Config\Services;

class MediaController extends BaseController
{
    protected MediaModel $mediaModel;
    protected ImageProcessor $imageProcessor;

    public function __construct()
    {
        $this->mediaModel     = new MediaModel();
        $this->imageProcessor = Services::imageProcessor();
    }

    public function index()
    {
        $term     = $this->request->getGet('q');
        $filetype = $this->request->getGet('type');
        $page     = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage  = 24;

        return view('admin/media/index', [
            'media' => $this->mediaModel->search($term, $filetype, $perPage, ($page - 1) * $perPage),
            'term'  => $term,
        ]);
    }

    public function upload()
    {
        $file = $this->request->getFile('file');

        if (! $file || ! $file->isValid()) {
            return $this->response->setJSON(['error' => 'No valid file uploaded.'])->setStatusCode(422);
        }

        // PRD §9 "File upload validation" — allow-list by extension/MIME,
        // never trust the client-supplied original name/type.
        if (! in_array($file->getClientExtension(), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'avif', 'svg', 'pdf', 'mp4', 'mp3'], true)) {
            return $this->response->setJSON(['error' => 'File type not allowed.'])->setStatusCode(422);
        }

        $yearMonth = date('Y/m');
        $targetDir = FCPATH . 'uploads/' . $yearMonth;

        if (! is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $originalName = $file->getClientName();

        // Keep the name the file actually arrived with (sanitized) instead
        // of the framework's random hash — descriptive filenames matter for
        // image SEO and let an admin recognize a file at a glance later.
        // Collisions (two different uploads landing on the same sanitized
        // name in the same month) get a -2/-3 suffix, same idea WordPress
        // uses, checked against the real filesystem rather than the DB
        // (see MediaModel's validation comment on why).
        $newName = $this->uniqueFilenameInDir($targetDir, $this->sanitizeFilename($originalName));

        $file->move($targetDir, $newName);

        $relativePath = 'uploads/' . $yearMonth . '/' . $newName;
        $absolutePath = $targetDir . '/' . $newName;

        // Auto-resize/compress + WebP + thumbnails, from whatever format
        // this server's GD build can decode — see ImageProcessor's class
        // docblock. A format it bridged (BMP/AVIF/…) comes back with a
        // *different* path (re-encoded to .png), which is why the fields
        // below all read from $processed rather than the pre-upload
        // $newName/$relativePath. Non-image uploads (PDF, audio…) pass
        // through unchanged with empty variants.
        $processed = $this->imageProcessor->process($absolutePath, $relativePath);
        $finalName = basename($processed['relativePath']);
        $wasBridged = $processed['relativePath'] !== $relativePath;

        $altText = (string) $this->request->getPost('alt_text');

        if ($altText === '') {
            $altText = $this->imageProcessor->altTextFromFilename($originalName);
        }

        $id = $this->mediaModel->insert([
            'filename'    => $finalName,
            'title'       => $this->imageProcessor->altTextFromFilename($originalName),
            'filepath'    => $processed['relativePath'],
            // A bridged upload's bytes are now actually PNG, whatever MIME
            // type the browser originally reported for the source format.
            'filetype'    => $wasBridged ? 'image/png' : $file->getClientMimeType(),
            'filesize'    => @filesize($processed['absolutePath']) ?: $file->getSize(),
            'width'       => $processed['width'],
            'height'      => $processed['height'],
            'variants'    => $processed['variants'] === [] ? null : $processed['variants'],
            'alt_text'    => $altText,
            'uploaded_by' => session('userId'),
        ], true);

        (new ActivityLogModel())->record(session('userId'), 'upload', 'media', $id);

        return $this->response->setJSON([
            'id'       => $id,
            'url'      => base_url($processed['relativePath']),
            'alt'      => $altText,
            'variants' => array_map(static fn ($path) => base_url($path), $processed['variants']),
        ]);
    }

    /**
     * JSON listing for the block editor's "Media Library" picker.
     * ?type=image|video|audio filters by MIME prefix.
     */
    public function listJson()
    {
        $type  = (string) $this->request->getGet('type');
        $query = $this->mediaModel->orderBy('created_at', 'DESC');

        if (in_array($type, ['image', 'video', 'audio'], true)) {
            $query = $query->like('filetype', $type . '/', 'after');
        }

        $items = array_map(static function (array $row) {
            $variants = $row['variants'] ?? [];

            return [
                'id'       => (int) $row['id'],
                'url'      => base_url($row['filepath']),
                'thumb'    => ! empty($variants['thumbnail']) ? base_url($variants['thumbnail']) : base_url($row['filepath']),
                'alt'      => $row['alt_text'] ?? '',
                'title'    => $row['title'] ?? '',
                'filename' => $row['filename'],
                'filetype' => $row['filetype'],
                'width'    => $row['width'],
                'height'   => $row['height'],
            ];
        }, $query->findAll(200));

        return $this->response->setJSON(['items' => $items]);
    }

    /**
     * Media Library's edit panel: title, alt text, and — unlike a typical
     * WordPress-style library — the on-disk filename itself, since the
     * upload flow now keeps the original name (see upload()) and admins
     * reasonably expect to be able to tidy/fix it afterward the same way.
     * Renaming moves the real file (and every generated variant) on disk;
     * everything else is a plain field update.
     */
    public function update(int $id)
    {
        $media = $this->mediaModel->find($id);

        if ($media === null) {
            return $this->response->setJSON(['error' => 'Not found.'])->setStatusCode(404);
        }

        $data = [
            'title'    => (string) $this->request->getPost('title'),
            'alt_text' => (string) $this->request->getPost('alt_text'),
        ];

        $newBaseName = trim((string) $this->request->getPost('filename'));
        $renamed     = null;

        if ($newBaseName !== '') {
            $renamed = $this->renameOnDisk($media, $newBaseName);

            if ($renamed === null) {
                return $this->response->setJSON(['error' => 'Could not rename the file.'])->setStatusCode(422);
            }

            $data = array_merge($data, $renamed);
        }

        if (! $this->mediaModel->update($id, $data)) {
            // The file(s) were already moved on disk above, but the
            // metadata save that was supposed to follow it just failed —
            // move them back rather than stranding a renamed file the DB
            // has no record of.
            if ($renamed !== null) {
                $this->undoRenameOnDisk($media, $renamed);
            }

            return $this->response->setJSON(['error' => implode(' ', $this->mediaModel->errors() ?: [])])->setStatusCode(422);
        }

        $fresh = $this->mediaModel->find($id);

        return $this->response->setJSON([
            'success'  => true,
            'title'    => $fresh['title'],
            'alt'      => $fresh['alt_text'],
            'filename' => $fresh['filename'],
            'url'      => base_url($fresh['filepath']),
        ]);
    }

    /** @deprecated kept for any external caller still hitting the old route; update() supersedes it. */
    public function updateAlt(int $id)
    {
        $this->mediaModel->update($id, ['alt_text' => (string) $this->request->getPost('alt_text')]);

        return $this->response->setJSON(['success' => true]);
    }

    public function delete(int $id)
    {
        $media = $this->mediaModel->find($id);

        if ($media) {
            @unlink(FCPATH . $media['filepath']);

            foreach ($media['variants'] ?? [] as $variantPath) {
                @unlink(FCPATH . $variantPath);
            }

            $this->mediaModel->delete($id);
            (new ActivityLogModel())->record(session('userId'), 'delete', 'media', $id);
        }

        return redirect()->back();
    }

    public function unused()
    {
        return view('admin/media/unused', ['media' => $this->mediaModel->findUnused()]);
    }

    /**
     * Renames a media row's file and every generated variant on disk, all
     * within the same uploads/YYYY/MM/ folder the file already lives in
     * (a rename never moves it to a different month). Returns the DB
     * fields to save (filename/filepath/variants), or null if the target
     * name is already taken by a different file in that folder.
     *
     * @return array{filename: string, filepath: string, variants: ?array<string, string>}|null
     */
    protected function renameOnDisk(array $media, string $newBaseName): ?array
    {
        $extension = pathinfo($media['filename'], PATHINFO_EXTENSION);
        $newName   = $this->sanitizeFilename($newBaseName . ($extension !== '' ? '.' . $extension : ''));

        if ($newName === $media['filename']) {
            return ['filename' => $media['filename'], 'filepath' => $media['filepath'], 'variants' => $media['variants']];
        }

        $dir = FCPATH . dirname($media['filepath']) . '/';

        if (is_file($dir . $newName)) {
            return null; // that name is already taken by a different file in this folder
        }

        $newRelativePath = dirname($media['filepath']) . '/' . $newName;

        // Build every on-disk move up front (primary first, then variants)
        // instead of doing the primary rename immediately and the variants
        // in a second, separate pass. A webp-native upload's own "webp"
        // variant is just an alias of the primary file — withNewExtension()
        // on a path that's already .webp returns that same path — so it
        // rides along with the primary move below instead of getting a
        // rename() of its own (that second rename used to run against a
        // source the primary move had already renamed away, fail, and
        // leave the DB's 'webp' entry pointing at a now-dead path).
        $moves       = [$media['filepath'] => $newRelativePath];
        $newVariants = null;

        foreach ($media['variants'] ?? [] as $key => $oldVariantPath) {
            if ($oldVariantPath === $media['filepath']) {
                $newVariants[$key] = $newRelativePath;
                continue;
            }

            $newVariantPath = $key === 'webp'
                ? $this->imageProcessor->withNewExtension($newRelativePath, 'webp')
                : $this->imageProcessor->withSuffix($newRelativePath, "-{$key}");

            $moves[$oldVariantPath] = $newVariantPath;
            $newVariants[$key]      = $newVariantPath;
        }

        // Apply every move, and if any single one fails partway through,
        // roll back the ones already done — never leave the file(s) split
        // across a mix of old and new names.
        $done = [];

        foreach ($moves as $from => $to) {
            if (! @rename(FCPATH . $from, FCPATH . $to)) {
                foreach ($done as $rolledFrom => $rolledTo) {
                    @rename(FCPATH . $rolledTo, FCPATH . $rolledFrom);
                }

                return null;
            }

            $done[$from] = $to;
        }

        return [
            'filename' => $newName,
            'filepath' => $newRelativePath,
            'variants' => $newVariants,
        ];
    }

    /** Reverses a successful renameOnDisk() when the DB save right after it fails. */
    protected function undoRenameOnDisk(array $originalMedia, array $renamed): void
    {
        if ($renamed['filepath'] !== $originalMedia['filepath']) {
            @rename(FCPATH . $renamed['filepath'], FCPATH . $originalMedia['filepath']);
        }

        foreach ($renamed['variants'] ?? [] as $key => $newPath) {
            $oldPath = $originalMedia['variants'][$key] ?? null;

            if ($oldPath !== null && $oldPath !== $newPath) {
                @rename(FCPATH . $newPath, FCPATH . $oldPath);
            }
        }
    }

    /**
     * Strips a client-supplied filename down to something safe to put on
     * disk and in a URL: keep the extension, ASCII-fold + slugify the base
     * name (spaces/anything unsafe -> "-"), and fall back to a generic
     * name if sanitizing leaves nothing usable (e.g. a name that was
     * entirely emoji or non-Latin symbols with nothing url_title() keeps).
     */
    protected function sanitizeFilename(string $original): string
    {
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        $base      = pathinfo($original, PATHINFO_FILENAME);
        $slug      = url_title($base, '-', true);

        if ($slug === '') {
            $slug = 'file-' . time();
        }

        return $extension !== '' ? "{$slug}.{$extension}" : $slug;
    }

    /** Appends -2, -3, … until $dir/$name doesn't already exist. */
    protected function uniqueFilenameInDir(string $dir, string $name): string
    {
        if (! is_file($dir . '/' . $name)) {
            return $name;
        }

        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $base      = pathinfo($name, PATHINFO_FILENAME);
        $i         = 2;

        do {
            $candidate = $extension !== '' ? "{$base}-{$i}.{$extension}" : "{$base}-{$i}";
            $i++;
        } while (is_file($dir . '/' . $candidate));

        return $candidate;
    }
}
