@extends('layouts.admin')
@section('title', 'Email')
@section('heading', 'Email (SMTP)')

@section('content')
    <x-alerts />

    <div class="row g-4">
        <div class="col-lg-7">
            <form method="POST" action="{{ route('admin.mail.update') }}">
                @csrf @method('PUT')

                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <span>SMTP server</span>
                        @if ($configured)
                            <span class="badge text-bg-success">Ready to send</span>
                        @else
                            <span class="badge text-bg-secondary">Not configured</span>
                        @endif
                    </div>

                    <div class="card-body row g-3">
                        <div class="col-md-7">
                            <label for="mail_smtp_host" class="form-label">SMTP host</label>
                            <input type="text" name="mail_smtp_host" id="mail_smtp_host" required
                                   value="{{ old('mail_smtp_host', $host) }}"
                                   class="form-control @error('mail_smtp_host') is-invalid @enderror">
                            @error('mail_smtp_host') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <div class="form-text">
                                Whatever your provider gives you — often
                                <code>mail.your-domain.com</code>, or something like
                                <code>smtp.provider.com</code> for a transactional service.
                            </div>
                        </div>

                        <div class="col-md-5">
                            <label for="mail_smtp_port" class="form-label">Port</label>
                            <select name="mail_smtp_port" id="mail_smtp_port"
                                    class="form-select @error('mail_smtp_port') is-invalid @enderror">
                                {{-- Both sides cast to string. PHP turns numeric array
                                     keys into integers, so $value is int 465 while the
                                     stored setting is the string '465' — a strict
                                     comparison matched nothing, no option was marked
                                     selected, and the browser fell back to the first
                                     one. Saving then wrote that back over the real
                                     choice. --}}
                                @foreach ($ports as $value => $label)
                                    <option value="{{ $value }}"
                                            @selected((string) old('mail_smtp_port', $port) === (string) $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('mail_smtp_port') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-12">
                            <label for="smtp_username" class="form-label">SMTP username</label>
                            <input type="text" name="smtp_username" id="smtp_username"
                                   autocomplete="off" placeholder="hello@your-domain.com"
                                   value="{{ old('smtp_username', $username) }}"
                                   class="form-control @error('smtp_username') is-invalid @enderror">
                            @error('smtp_username') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-12">
                            <label for="smtp_password" class="form-label">
                                SMTP password
                                @if ($passwordSet)
                                    <span class="badge text-bg-success ms-1">Stored</span>
                                @endif
                            </label>
                            <input type="password" name="smtp_password" id="smtp_password"
                                   autocomplete="new-password"
                                   placeholder="{{ $passwordSet ? 'Leave blank to keep the stored password' : 'Paste the SMTP password' }}"
                                   class="form-control @error('smtp_password') is-invalid @enderror">
                            @error('smtp_password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <div class="form-text">
                                Encrypted before it is stored and never shown again — blank means
                                “leave it as it is”. For a mailbox on your own hosting this is the
                                email account's password; a transactional service issues a separate
                                SMTP password instead.
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label for="mail_from_address" class="form-label">Sender address</label>
                            <input type="email" name="mail_from_address" id="mail_from_address" required
                                   value="{{ old('mail_from_address', $fromAddress) }}"
                                   class="form-control @error('mail_from_address') is-invalid @enderror">
                            @error('mail_from_address') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <div class="form-text">
                                Usually the same as the username. Most servers only allow sending
                                from the account that logged in.
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label for="mail_from_name" class="form-label">Sender name</label>
                            <input type="text" name="mail_from_name" id="mail_from_name" required
                                   value="{{ old('mail_from_name', $fromName) }}"
                                   class="form-control @error('mail_from_name') is-invalid @enderror">
                            @error('mail_from_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="card-footer">
                        <button class="btn btn-shop" data-confirm="Save the mail settings?">
                            Save settings
                        </button>
                    </div>
                </div>
            </form>

            <div class="card mt-4">
                <div class="card-header">Send a test message</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.mail.test') }}"
                          data-confirm="Send a real test email now?">
                        @csrf
                        <label for="test_to" class="form-label">Send to</label>
                        <div class="input-group">
                            <input type="email" name="test_to" id="test_to" required
                                   value="{{ old('test_to', auth()->user()?->email) }}"
                                   class="form-control @error('test_to') is-invalid @enderror">
                            <button class="btn btn-outline-primary" @disabled(! $configured)>
                                <i class="bi bi-send me-1"></i>Send test
                            </button>
                        </div>
                        @error('test_to') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </form>

                    <div class="form-text mt-2">
                        A real message, not a handshake. A server will accept a good password and
                        still refuse to send from an address it does not own, and only sending
                        finds that.
                        @unless ($configured)
                            <strong>Save a username, password and sender address first.</strong>
                        @endunless
                    </div>

                    <x-integration-test-result provider="smtp" />
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">Where to find these</div>
                <div class="card-body small">
                    <p><strong>Your own hosting (cPanel and similar):</strong> Email Accounts →
                        Connect Devices. Use the <em>outgoing</em> server and SMTP port; the IMAP
                        and POP3 settings are for reading mail and are not needed here.</p>
                    <p class="mb-0"><strong>A transactional service</strong> (Mailgun, Postmark,
                        Brevo and the like): look for SMTP credentials in the sending domain's
                        settings. These are usually a separate password, shown once.</p>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">If a test fails</div>
                <div class="card-body small">
                    <ul class="mb-0 ps-3 d-grid gap-2">
                        <li><strong>Authentication rejected</strong> — the username is usually the
                            full email address. If your provider issues a separate SMTP password,
                            use that rather than the account password.</li>
                        <li><strong>Timed out</strong> — the host is probably blocking that port
                            outbound. 2525 exists for exactly this; check the server name too.</li>
                        <li><strong>Secure connection failed</strong> — 465 expects TLS
                            immediately, while 587 and 2525 upgrade to it. Use the one your
                            provider asks for.</li>
                        <li><strong>Refused the message</strong> — most servers only let you send
                            from the address you logged in as. Match the sender to the username.</li>
                    </ul>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">What gets sent</div>
                <div class="card-body small">
                    <p>
                        <strong>Order confirmation</strong> — sent to the buyer once their payment
                        is verified, with the items, totals and delivery address.
                    </p>
                    <p>
                        While this screen says <strong>Not configured</strong>, nothing is sent
                        at all. Orders still complete normally; customers simply do not get an
                        email.
                    </p>
                    <p class="mb-0">
                        Send yourself a test and check the <strong>spam folder</strong>, not just
                        the inbox. A confirmation that arrives in spam has still failed. If mail
                        lands there, the fix is SPF and DKIM records on your domain rather than
                        anything on this screen.
                    </p>
                </div>
            </div>
        </div>
    </div>
@endsection
