<x-layout title="Privacy & terms">
<div class="page-heading"><span class="section-kicker">PLAIN AND SIMPLE</span><h1>Privacy & terms<span class="heading-dot">.</span></h1><p>What AllerScan keeps, and what it doesn’t. <span lang="ja">プライバシーと利用条件</span></p></div>
<div class="preferences-sections">
<section class="preference-section" id="privacy">
    <h2>Your information <span lang="ja">個人情報の取り扱い</span></h2>
    <p><strong>No shopper account.</strong> You can browse, scan, and save products without signing up. Your chosen allergens, theme, text size, voice choices, and saved products are stored only in this browser’s local storage. They are not sent to our database and do not sync between devices. Clearing your browser data removes them.</p>
    <p><strong>Saved products.</strong> When you open Saved, your browser sends the saved product numbers to our server so it can return current product information. The numbers are not stored with your identity.</p>
    <p><strong>Camera.</strong> Barcode scanning runs in your browser. Camera images are not uploaded or recorded. You can deny or revoke camera permission at any time and enter a barcode by hand instead.</p>
    <p><strong>Voice.</strong> Reading aloud uses your device’s speech voices. Voices marked “online” are provided by your browser or operating system vendor and may send the text being read to that vendor.</p>
    <p><strong>Cookies and logs.</strong> We use a session cookie and a security (CSRF) cookie needed for the site and team sign-in to work. We do not use advertising or analytics cookies. Like most websites, our server records technical logs (such as IP address, time, and page requested) to keep the service secure. Logs are kept only as long as needed for that purpose.</p>
    <p><strong>Team accounts.</strong> Catalog staff accounts store a name, email address, and a securely hashed password.</p>
    <p lang="ja">買い物のためのアカウントは不要です。アレルゲン設定や保存した商品はこのブラウザ内にのみ保存され、当サービスのデータベースには送信されません。カメラ映像はブラウザ内で処理され、アップロードされません。</p>
</section>
<section class="preference-section" id="terms">
    <h2>Terms of use <span lang="ja">利用条件</span></h2>
    <p><strong>Not medical or dietary advice.</strong> AllerScan is a graduation project demo. Product and allergen records may be fictional, incomplete, or out of date. A product without a highlighted match, or with no recorded allergens, is not necessarily allergen-free. Always check the actual package label and manufacturer information, and consult a medical professional about your allergies.</p>
    <p>The service is provided “as is” and may change or stop without notice. We are not liable for decisions made using information shown here.</p>
    <p lang="ja">本サービスは卒業制作のデモであり、医療・食事に関する助言ではありません。掲載情報は架空・不完全・古い場合があります。必ず実際の商品表示とメーカー情報をご確認ください。</p>
</section>
@if(config('demo.contact_email'))
<section class="preference-section" id="contact">
    <h2>Contact <span lang="ja">お問い合わせ</span></h2>
    <p>Questions about privacy or this service: <a href="mailto:{{ config('demo.contact_email') }}">{{ config('demo.contact_email') }}</a></p>
</section>
@endif
</div>
</x-layout>
