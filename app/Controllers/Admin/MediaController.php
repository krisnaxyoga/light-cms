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
        if (! in_array($file->getClientExtension(), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'pdf', 'mp4', 'mp3'], true)) {
            return $this->response->setJSON(['error' => 'File type not allowed.'])->setStatusCode(422);
        }

        $yearMonth  = date('Y/m');
        $targetDir  = FCPATH . 'uploads/' . $yearMonth;
        if (! is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $newName      = $file->getRandomName();
        $originalName = $file->getClientName();
        $file->move($targetDir, $newName);

        $relativePath = 'uploads/' . $yearMonth . '/' . $newName;
        $absolutePath = $targetDir . '/' . $newName;

        // Auto-resize/compress + WebP + thumbnails; non-image uploads
        // (PDF, audio…) pass through with empty variants (PRD ADDENDUM §4).
        $processed = $this->imageProcessor->process($absolutePath, $relativePath);

        $altText = (string) $this->request->getPost('alt_text');

        if ($altText === '') {
            $altText = $this->imageProcessor->altTextFromFilename($originalName);
        }

        $id = $this->mediaModel->insert([
            'filename'    => $newName,
            'filepath'    => $relativePath,
            'filetype'    => $file->getClientMimeType(),
            'filesize'    => $file->getSize(),
            'width'       => $processed['width'],
            'height'      => $processed['height'],
            'variants'    => $processed['variants'] === [] ? null : $processed['variants'],
            'alt_text'    => $altText,
            'uploaded_by' => session('userId'),
        ], true);

        (new ActivityLogModel())->record(session('userId'), 'upload', 'media', $id);

        return $this->response->setJSON([
            'id'       => $id,
            'url'      => base_url($relativePath),
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
                'filename' => $row['filename'],
                'filetype' => $row['filetype'],
                'width'    => $row['width'],
                'height'   => $row['height'],
            ];
        }, $query->findAll(200));

        return $this->response->setJSON(['items' => $items]);
    }

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
}
