@extends('layouts.admin')
@section('title', 'Email')
@section('heading', 'Email (Mailgun)')

@section('content')
    <x-alerts />

    <div class="row g-4">
        <div class="col-lg-7">
            <form method="POST" action="{{ route('admin.mail.update') }}">
                @csrf @method('PUT')

                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <span>Mailgun SMTP</span>
                        @if ($configured)
                            <span class="badge text-bg-success">Ready to send</span>
                        @else
                            <span class="badge text-bg-secondary">Not configured</span>
                        @endif
                    </div>

                    <div class="card-body row g-3">
                        <div class="col-md-7">
                            <label for="mailgun_smtp_host" class="form-label">SMTP host</label>
                            <input type="text" name="mailgun_smtp_host" id="mailgun_smtp_host" required
                                   value="{{ old('mailgun_smtp_host', $host) }}"
                                   class="form-control @error('mailgun_smtp_host') is-invalid @enderror">
                            @error('mailgun_smtp_host') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <div class="form-text">
                                <code>smtp.mailgun.org</code>, or <code>smtp.eu.mailgun.org</code>
                                if your domain is in Mailgun's EU region.
                            </div>
                        </div>

                        <div class="col-md-5">
                            <label for="mailgun_smtp_port" class="form-label">Port</label>
                            <select name="mailgun_smtp_port" id="mailgun_smtp_port"
                                    class="form-select @error('mailgun_smtp_port') is-invalid @enderror">
                                @foreach ($ports as $value => $label)
                                    <option value="{{ $value }}" @selected(old('mailgun_smtp_port', $port) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('mailgun_smtp_port') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-12">
                            <label for="smtp_username" class="form-label">SMTP username</label>
                            <input type="text" name="smtp_username" id="smtp_username"
                                   autocomplete="off" placeholder="postmaster@mg.your-domain.com"
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
                                “leave it as it is”. This is <strong>not</strong> your Mailgun
                                account password: copy it from Sending → Domain settings → SMTP credentials.
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label for="mail_from_address" class="form-label">Sender address</label>
                            <input type="email" name="mail_from_address" id="mail_from_address" required
                                   value="{{ old('mail_from_address', $fromAddress) }}"
                                   class="form-control @error('mail_from_address') is-invalid @enderror">
                            @error('mail_from_address') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <div class="form-text">Must be on a domain verified in Mailgun.</div>
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
                        A real message, not a handshake. Mailgun will accept a good password and
                        still refuse to send from an unverified domain, and only sending finds that.
                        @unless ($configured)
                            <strong>Save a username, password and sender address first.</strong>
                        @endunless
                    </div>

                    <x-integration-test-result provider="mailgun" />
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">Where to find these</div>
                <div class="card-body small">
                    <p>In Mailgun: <strong>Sending → Domains</strong>, pick your domain, then
                        <strong>Domain settings → SMTP credentials</strong>.</p>
                    <p class="mb-0">The username looks like
                        <code>postmaster@mg.your-domain.com</code>. Reset the password there if
                        you no longer have it — it is only shown once.</p>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">If a test fails</div>
                <div class="card-body small">
                    <ul class="mb-0 ps-3 d-grid gap-2">
                        <li><strong>Authentication rejected</strong> — the SMTP password is not the
                            account password. Copy it from Domain settings.</li>
                        <li><strong>Timed out</strong> — the host is probably blocking port 587
                            outbound. Switch to 2525, which exists for exactly this.</li>
                        <li><strong>Refused the message</strong> — the sender address must be on a
                            domain verified in Mailgun. A sandbox domain can only send to
                            addresses you have authorised there.</li>
                    </ul>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">Note</div>
                <div class="card-body small">
                    <p class="mb-0">
                        Nothing in the shop sends email yet — order confirmations are not built.
                        These settings prepare the transport, and the test button proves it works.
                    </p>
                </div>
            </div>
        </div>
    </div>
@endsection
