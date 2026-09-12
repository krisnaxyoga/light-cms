<?php

namespace Tests\Database;

use App\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * The account screen: profile edits, the current-password gate on
 * identity changes, and password changes.
 *
 * Driven through real requests (FeatureTestTrait) rather than by calling
 * the controller directly, because the rules that matter here — the CSRF
 * filter, the auth filter, session state after a credential change — only
 * exist on the request path.
 */
final class ProfileTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;

    /** null = run migrations from every namespace, App included. */
    protected $namespace = null;

    private int $userId;

    protected function setUp(): void
    {
        // Checked before parent::setUp(), which runs the migrations: the
        // LightCMS schema is MySQL-specific. Point the tests group at MySQL
        // to run these (see tests/database/WordPressContentTest.php).
        $driver = (string) (config('Database')->tests['DBDriver'] ?? '');

        if (! str_contains(strtolower($driver), 'mysql')) {
            $this->markTestSkipped('Needs a MySQL "tests" connection; the LightCMS migrations are MySQL-only.');
        }

        parent::setUp();

        $this->db->table('roles')->insert(['id' => 2, 'name' => 'administrator', 'created_at' => date('Y-m-d H:i:s')]);

        $this->userId = (int) (new UserModel())->insert([
            'username'     => 'admin',
            'email'        => 'admin@example.test',
            'password'     => 'CurrentPass123',
            'display_name' => 'Administrator',
            'role_id'      => 2,
            'status'       => 'active',
        ], true);
    }

    private function signedIn(): array
    {
        return ['isLoggedIn' => true, 'userId' => $this->userId, 'username' => 'admin', 'displayName' => 'Administrator'];
    }

    /** Admin POSTs go through the CSRF filter, so every payload carries a token. */
    private function form(array $fields): array
    {
        return ['csrf_test_name' => csrf_hash()] + $fields;
    }

    private function row(): array
    {
        return (array) $this->db->table('users')->where('id', $this->userId)->get()->getRowArray();
    }

    public function testProfileScreenNeedsAuthentication(): void
    {
        $result = $this->get('admin/profile');

        $result->assertRedirectTo('/admin/login');
    }

    public function testProfileScreenShowsTheCurrentAccount(): void
    {
        $result = $this->withSession($this->signedIn())->get('admin/profile');

        $result->assertOK();
        $result->assertSeeInField('email', 'admin@example.test');
        $result->assertSeeInField('username', 'admin');
        $result->assertSeeInField('display_name', 'Administrator');

        // The admin shell: daisyUI stylesheet + the theme attribute it needs.
        $body = $result->getBody();
        $this->assertStringContainsString('assets/admin/css/admin-ui.css', $body);
        $this->assertStringContainsString('data-theme="lightcms"', $body);
    }

    public function testDisplayNameChangeNeedsNoPassword(): void
    {
        $result = $this->withSession($this->signedIn())->post('admin/profile', $this->form([
            'display_name' => 'Admin Utama',
            'username'     => 'admin',
            'email'        => 'admin@example.test',
            'avatar'       => '',
        ]));

        $result->assertRedirectTo('/admin/profile');
        $this->assertSame('Admin Utama', $this->row()['display_name']);
    }

    public function testEmailChangeIsRefusedWithoutTheCurrentPassword(): void
    {
        $this->withSession($this->signedIn())->post('admin/profile', $this->form([
            'display_name' => 'Administrator',
            'username'     => 'admin',
            'email'        => 'attacker@example.test',
            'avatar'       => '',
        ]));

        $this->assertSame('admin@example.test', $this->row()['email'], 'The email must not move without the password.');
    }

    public function testEmailChangeIsRefusedWithTheWrongPassword(): void
    {
        $this->withSession($this->signedIn())->post('admin/profile', $this->form([
            'display_name'     => 'Administrator',
            'username'         => 'admin',
            'email'            => 'attacker@example.test',
            'avatar'           => '',
            'confirm_password' => 'not-the-password',
        ]));

        $this->assertSame('admin@example.test', $this->row()['email']);
    }

    public function testEmailAndUsernameChangeSucceedsWithTheCurrentPassword(): void
    {
        $result = $this->withSession($this->signedIn())->post('admin/profile', $this->form([
            'display_name'     => 'Administrator',
            'username'         => 'admin2',
            'email'            => 'new@example.test',
            'avatar'           => '/uploads/2026/09/me.jpg',
            'confirm_password' => 'CurrentPass123',
        ]));

        $result->assertRedirectTo('/admin/profile');

        $row = $this->row();
        $this->assertSame('admin2', $row['username']);
        $this->assertSame('new@example.test', $row['email']);
        $this->assertSame('/uploads/2026/09/me.jpg', $row['avatar']);
    }

    public function testEmailAlreadyUsedByAnotherAccountIsRejected(): void
    {
        (new UserModel())->insert([
            'username' => 'editor',
            'email'    => 'editor@example.test',
            'password' => 'EditorPass123',
            'role_id'  => 2,
            'status'   => 'active',
        ]);

        $this->withSession($this->signedIn())->post('admin/profile', $this->form([
            'display_name'     => 'Administrator',
            'username'         => 'admin',
            'email'            => 'editor@example.test',
            'avatar'           => '',
            'confirm_password' => 'CurrentPass123',
        ]));

        $this->assertSame('admin@example.test', $this->row()['email']);
    }

    public function testSavingAnUnchangedProfileDoesNotTripItsOwnUniquenessRules(): void
    {
        $result = $this->withSession($this->signedIn())->post('admin/profile', $this->form([
            'display_name'     => 'Administrator',
            'username'         => 'admin',
            'email'            => 'admin@example.test',
            'avatar'           => '',
        ]));

        $result->assertRedirectTo('/admin/profile');
        $this->assertSame('admin', $this->row()['username']);
    }

    /**
     * @dataProvider rejectedPasswordChanges
     */
    public function testInvalidPasswordChangesAreRejected(string $current, string $new, string $confirm): void
    {
        $before = $this->row()['password'];

        $this->withSession($this->signedIn())->post('admin/profile/password', $this->form([
            'current_password'     => $current,
            'new_password'         => $new,
            'new_password_confirm' => $confirm,
        ]));

        $this->assertSame($before, $this->row()['password'], 'The stored hash must be untouched.');
        $this->assertTrue(password_verify('CurrentPass123', $this->row()['password']));
    }

    public static function rejectedPasswordChanges(): array
    {
        return [
            'wrong current password' => ['WrongPass123', 'BrandNewPass123', 'BrandNewPass123'],
            'confirmation mismatch'  => ['CurrentPass123', 'BrandNewPass123', 'DifferentPass123'],
            'too short'              => ['CurrentPass123', 'short7', 'short7'],
            'over the bcrypt limit'  => ['CurrentPass123', str_repeat('a', 73), str_repeat('a', 73)],
            'same as the old one'    => ['CurrentPass123', 'CurrentPass123', 'CurrentPass123'],
            'equal to the email'     => ['CurrentPass123', 'admin@example.test', 'admin@example.test'],
        ];
    }

    public function testPasswordChangeStoresANewHashAndKeepsTheUserSignedIn(): void
    {
        $before = $this->row()['password'];

        $result = $this->withSession($this->signedIn())->post('admin/profile/password', $this->form([
            'current_password'     => 'CurrentPass123',
            'new_password'         => 'BrandNewPass123',
            'new_password_confirm' => 'BrandNewPass123',
        ]));

        $result->assertRedirectTo('/admin/profile');

        $after = $this->row()['password'];

        $this->assertNotSame($before, $after);
        $this->assertTrue(password_verify('BrandNewPass123', $after), 'The new password must be stored as a bcrypt hash.');
        $this->assertFalse(password_verify('CurrentPass123', $after), 'The old password must stop working.');

        // And the account still authenticates through the normal login path.
        $this->assertNotNull((new UserModel())->verifyCredentials('admin', 'BrandNewPass123'));
        $this->assertNull((new UserModel())->verifyCredentials('admin', 'CurrentPass123'));
    }

    public function testThePasswordHashIsNeverRenderedOnTheScreen(): void
    {
        $result = $this->withSession($this->signedIn())->get('admin/profile');

        $result->assertOK();
        $this->assertStringNotContainsString(
            substr($this->row()['password'], 0, 20),
            $result->getBody(),
            'The bcrypt hash must never reach the browser.'
        );
    }
}
