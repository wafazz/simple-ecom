<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The mail screen was built around Mailgun and is now a plain SMTP form, since
 * any provider — or the shop's own cPanel mailbox — works the same way.
 *
 * The stored keys are renamed with it. A shop that has already saved
 * credentials must not lose them to a relabelling, so the rows are moved rather
 * than dropped and recreated.
 */
return new class extends Migration
{
    /** old key => new key */
    private const SECURE = [
        'mailgun.smtp_username' => 'mail.smtp_username',
        'mailgun.smtp_password' => 'mail.smtp_password',
    ];

    private const PLAIN = [
        'mailgun_smtp_host' => 'mail_smtp_host',
        'mailgun_smtp_port' => 'mail_smtp_port',
    ];

    public function up(): void
    {
        $this->move(self::SECURE, 'secure_settings');
        $this->move(self::PLAIN, 'settings');
    }

    public function down(): void
    {
        $this->move(array_flip(self::SECURE), 'secure_settings');
        $this->move(array_flip(self::PLAIN), 'settings');
    }

    /**
     * Both tables carry a UNIQUE key, so a destination row left over from an
     * earlier run would collide. The newer value wins and the old row goes.
     *
     * @param  array<string, string>  $keys
     */
    private function move(array $keys, string $table): void
    {
        foreach ($keys as $from => $to) {
            $source = DB::table($table)->where('key', $from)->first();

            if ($source === null) {
                continue;
            }

            DB::table($table)->where('key', $to)->delete();
            DB::table($table)->where('key', $from)->update(['key' => $to]);
        }
    }
};
