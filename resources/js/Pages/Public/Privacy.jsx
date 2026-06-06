import { useState } from 'react';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link } from '@inertiajs/react';

/**
 * Public privacy policy. Two languages — English (default) and Bahasa
 * Malaysia — both kept in this single file because they need to stay in
 * lockstep when sub-processors / retention windows / DPO contact change.
 *
 * The body is intentionally plain content rather than rendered Markdown:
 * it makes it trivial for legal review to diff edits in the source, and
 * we don't want to ship a markdown runtime just for one page.
 */
export default function Privacy({ version, dpoEmail, controller }) {
    const [lang, setLang] = useState('en');

    return (
        <GuestLayout wide>
            <Head title="Privacy policy" />

            <div className="max-w-3xl mx-auto py-10 px-4">
                <div className="flex items-center justify-between mb-6 flex-wrap gap-3">
                    <div>
                        <p className="text-eyebrow font-semibold uppercase text-terracotta">Privacy</p>
                        <h1 className="font-display text-3xl lg:text-4xl font-medium text-ink tracking-tight">
                            Privacy policy
                        </h1>
                        <p className="text-ink-muted text-sm mt-1">Version {version}</p>
                    </div>
                    <div className="bg-surface p-1 rounded-xl border border-border-warm inline-flex">
                        <button
                            type="button"
                            onClick={() => setLang('en')}
                            className={`px-4 py-1.5 rounded-lg text-sm font-semibold transition-colors ${
                                lang === 'en' ? 'bg-ink text-cream' : 'text-ink-muted hover:text-ink'
                            }`}
                        >
                            English
                        </button>
                        <button
                            type="button"
                            onClick={() => setLang('bm')}
                            className={`px-4 py-1.5 rounded-lg text-sm font-semibold transition-colors ${
                                lang === 'bm' ? 'bg-ink text-cream' : 'text-ink-muted hover:text-ink'
                            }`}
                        >
                            Bahasa Malaysia
                        </button>
                    </div>
                </div>

                <article className="prose prose-sm max-w-none text-ink">
                    {lang === 'en' ? <PolicyEN dpoEmail={dpoEmail} controller={controller} /> : <PolicyBM dpoEmail={dpoEmail} controller={controller} />}
                </article>

                <div className="mt-10 pt-6 border-t border-border-warm text-sm text-ink-muted">
                    <Link href={route('login')} className="text-terracotta hover:text-terracotta-dark font-semibold">
                        ← Back to sign in
                    </Link>
                </div>
            </div>
        </GuestLayout>
    );
}

function PolicyEN({ dpoEmail, controller }) {
    return (
        <>
            <p>
                {controller} (“we”, “us”, “BukuCloud”) operates the BukuCloud accounting platform.
                This policy explains what personal data we collect, how we use it, and the rights you
                have under the Personal Data Protection Act 2010 (Malaysia) and its 2024 amendments.
            </p>

            <h2 className="font-display text-xl mt-6 mb-2">1. Data we collect</h2>
            <ul className="list-disc pl-5 space-y-1">
                <li><strong>Account data:</strong> name, email, phone number, role, password (hashed only).</li>
                <li><strong>Company data:</strong> business name, registration / SST / TIN numbers, address, banking details for invoice display.</li>
                <li><strong>Customer / supplier records you create:</strong> names, emails, phones, addresses, financial transactions.</li>
                <li><strong>Receipts and invoices:</strong> uploaded images and PDFs, line-item OCR data extracted from them.</li>
                <li><strong>Usage telemetry:</strong> IP address, user-agent, login timestamps, audit log of actions taken.</li>
            </ul>

            <h2 className="font-display text-xl mt-6 mb-2">2. How we use it</h2>
            <ul className="list-disc pl-5 space-y-1">
                <li>To provide the accounting software you signed up for.</li>
                <li>To send invoices, statements, and reminders on your behalf to your customers.</li>
                <li>To run optical character recognition on receipts you upload (default: local Tesseract; only Google Gemini if your tenant explicitly enables it in settings).</li>
                <li>To process subscription payments through ToyyibPay.</li>
                <li>To meet legal record-keeping obligations under the Income Tax Act 1967 (7 years for financial records).</li>
                <li>To investigate suspected security incidents or abuse.</li>
            </ul>

            <h2 className="font-display text-xl mt-6 mb-2">3. Sub-processors</h2>
            <p>We share the minimum data necessary with these vendors:</p>
            <ul className="list-disc pl-5 space-y-1">
                <li><strong>Amazon Web Services</strong> — application hosting and encrypted backups (Singapore region).</li>
                <li><strong>Google (Gemini)</strong> — optional OCR provider; receipt images are sent only when your tenant has enabled the Gemini provider in settings. Default is on-device Tesseract with no third-party transfer.</li>
                <li><strong>ToyyibPay</strong> — Malaysian payment gateway for subscription billing; receives your name, email, phone, and bill amount only.</li>
            </ul>

            <h2 className="font-display text-xl mt-6 mb-2">4. Retention</h2>
            <p>
                Active account data is kept while your account is active. After deletion, financial
                records (invoices, bills, journal entries) are retained for 7 years to satisfy the
                Income Tax Act 1967, with personally identifying fields (customer / supplier names,
                contacts) redacted. Audit logs are kept for 18 months. Failed payment attempts are
                kept for 30 days. Receipts you uploaded follow your tenant's retention policy.
            </p>

            <h2 className="font-display text-xl mt-6 mb-2">5. Your rights</h2>
            <ul className="list-disc pl-5 space-y-1">
                <li><strong>Access:</strong> download a copy of your data from <code>Settings → Data export</code>.</li>
                <li><strong>Correction:</strong> edit your profile and company information at any time.</li>
                <li><strong>Erasure:</strong> request account deletion from <code>Settings → Delete account</code>. There is a 30-day cooling-off period before hard deletion.</li>
                <li><strong>Withdraw consent:</strong> email us at <a href={`mailto:${dpoEmail}`} className="text-terracotta">{dpoEmail}</a>.</li>
                <li><strong>Lodge a complaint:</strong> with the Personal Data Protection Department (Jabatan Perlindungan Data Peribadi).</li>
            </ul>

            <h2 className="font-display text-xl mt-6 mb-2">6. Security</h2>
            <p>
                Data is transmitted over TLS, stored in encrypted-at-rest databases (AES-256), and
                isolated per tenant. Passwords are hashed using bcrypt. Receipt files are stored on
                private storage and served only to authenticated members of the owning tenant.
                Suspected breaches are reported to the PDPC within 72 hours where required.
            </p>

            <h2 className="font-display text-xl mt-6 mb-2">7. Contact</h2>
            <p>
                Data Protection Officer:{' '}
                <a href={`mailto:${dpoEmail}`} className="text-terracotta">{dpoEmail}</a>
            </p>
        </>
    );
}

function PolicyBM({ dpoEmail, controller }) {
    return (
        <>
            <p>
                {controller} (“kami”, “BukuCloud”) mengendalikan platform perakaunan BukuCloud.
                Dasar ini menerangkan data peribadi yang kami kumpul, cara kami menggunakannya, dan
                hak anda di bawah Akta Perlindungan Data Peribadi 2010 (Malaysia) berserta pindaan 2024.
            </p>

            <h2 className="font-display text-xl mt-6 mb-2">1. Data yang dikumpul</h2>
            <ul className="list-disc pl-5 space-y-1">
                <li><strong>Data akaun:</strong> nama, e-mel, nombor telefon, peranan, kata laluan (disimpan secara hash sahaja).</li>
                <li><strong>Data syarikat:</strong> nama perniagaan, nombor pendaftaran / SST / TIN, alamat, butiran perbankan untuk paparan invois.</li>
                <li><strong>Rekod pelanggan / pembekal yang anda cipta:</strong> nama, e-mel, telefon, alamat, transaksi kewangan.</li>
                <li><strong>Resit dan invois:</strong> imej dan PDF yang dimuat naik, data OCR baris item yang diekstrak.</li>
                <li><strong>Telemetri penggunaan:</strong> alamat IP, ejen pengguna, cap masa log masuk, log audit tindakan.</li>
            </ul>

            <h2 className="font-display text-xl mt-6 mb-2">2. Cara kami menggunakannya</h2>
            <ul className="list-disc pl-5 space-y-1">
                <li>Untuk menyediakan perisian perakaunan yang anda daftarkan.</li>
                <li>Untuk menghantar invois, penyata, dan peringatan kepada pelanggan anda bagi pihak anda.</li>
                <li>Untuk menjalankan OCR pada resit yang anda muat naik (lalai: Tesseract tempatan; Google Gemini hanya jika tenant anda mengaktifkannya).</li>
                <li>Untuk memproses bayaran langganan melalui ToyyibPay.</li>
                <li>Untuk memenuhi obligasi penyimpanan rekod di bawah Akta Cukai Pendapatan 1967 (7 tahun bagi rekod kewangan).</li>
                <li>Untuk menyiasat insiden keselamatan atau penyalahgunaan yang disyaki.</li>
            </ul>

            <h2 className="font-display text-xl mt-6 mb-2">3. Pemproses pihak ketiga</h2>
            <p>Kami berkongsi data minimum yang diperlukan dengan vendor berikut:</p>
            <ul className="list-disc pl-5 space-y-1">
                <li><strong>Amazon Web Services</strong> — hos aplikasi dan sandaran disulitkan (rantau Singapura).</li>
                <li><strong>Google (Gemini)</strong> — pembekal OCR pilihan; imej resit dihantar hanya jika tenant anda mengaktifkan Gemini.</li>
                <li><strong>ToyyibPay</strong> — gateway pembayaran Malaysia untuk bil langganan.</li>
            </ul>

            <h2 className="font-display text-xl mt-6 mb-2">4. Tempoh penyimpanan</h2>
            <p>
                Data akaun aktif disimpan selagi akaun anda aktif. Selepas pemadaman, rekod kewangan
                (invois, bil, entri jurnal) disimpan selama 7 tahun untuk memenuhi Akta Cukai
                Pendapatan 1967, dengan medan pengenalan diri ditapis. Log audit disimpan selama 18
                bulan. Cubaan bayaran yang gagal disimpan selama 30 hari.
            </p>

            <h2 className="font-display text-xl mt-6 mb-2">5. Hak anda</h2>
            <ul className="list-disc pl-5 space-y-1">
                <li><strong>Akses:</strong> muat turun salinan data anda dari <code>Tetapan → Eksport data</code>.</li>
                <li><strong>Pembetulan:</strong> kemas kini profil dan maklumat syarikat anda pada bila-bila masa.</li>
                <li><strong>Pemadaman:</strong> minta pemadaman akaun dari <code>Tetapan → Padam akaun</code>. Terdapat tempoh bertenang 30 hari sebelum pemadaman kekal.</li>
                <li><strong>Tarik balik kebenaran:</strong> e-mel kami di <a href={`mailto:${dpoEmail}`} className="text-terracotta">{dpoEmail}</a>.</li>
                <li><strong>Buat aduan:</strong> kepada Jabatan Perlindungan Data Peribadi.</li>
            </ul>

            <h2 className="font-display text-xl mt-6 mb-2">6. Keselamatan</h2>
            <p>
                Data dihantar melalui TLS, disimpan dalam pangkalan data yang disulitkan (AES-256),
                dan diasingkan setiap tenant. Kata laluan disimpan menggunakan bcrypt. Fail resit
                disimpan secara peribadi dan hanya dihidangkan kepada ahli tenant yang sah.
                Pencerobohan yang disyaki dilaporkan kepada PDPC dalam 72 jam jika diperlukan.
            </p>

            <h2 className="font-display text-xl mt-6 mb-2">7. Hubungi kami</h2>
            <p>
                Pegawai Perlindungan Data:{' '}
                <a href={`mailto:${dpoEmail}`} className="text-terracotta">{dpoEmail}</a>
            </p>
        </>
    );
}
