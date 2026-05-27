<?php
declare(strict_types=1);

const UI_BRAND_NAME = 'Lead Bank';
const UI_BRAND_SHORT = 'Lead Bank';

function lead_logo(string $tone = 'dark'): string
{
    $brand = current_brand_config();
    $toneClass = $tone === 'light' ? 'brand-mark-light' : 'brand-mark-dark';
    $brandClass = 'brand-mark-' . e((string) $brand['brand_key']);
    return '<span class="brand-mark ' . $toneClass . ' ' . $brandClass . '"><span class="brand-symbol" aria-hidden="true"><img src="' . e(url((string) $brand['logo_mark'])) . '" alt=""></span><span>' . e((string) $brand['brand_short_name']) . '</span></span>';
}

function lead_nav_item(string $href, string $icon, string $label, bool $active = false, bool $disabled = false): string
{
    $classes = 'nav-link' . ($active ? ' active' : '') . ($disabled ? ' disabled restricted-link' : '');
    $url = $disabled ? '#' : url($href);
    $attrs = $disabled ? ' aria-disabled="true" title="Unavailable while account access is restricted"' : '';
    $lock = $disabled ? '<span class="ms-auto"><i class="fa-solid fa-lock"></i></span>' : '';
    return '<a class="' . e($classes) . '" href="' . e($url) . '"' . $attrs . '><i class="fa-solid ' . e($icon) . '"></i><span>' . e($label) . '</span>' . $lock . '</a>';
}

function deposit_protection_config_for_user(array $user, ?array $account = null): array
{
    $configPath = __DIR__ . '/../config/deposit_protection.php';
    $config = is_file($configPath) ? require $configPath : [];
    $default = $config['default'] ?? [
        'agency' => 'Deposit Protection',
        'name' => 'Deposit Protection',
        'text' => 'Deposits protected up to applicable limits.',
    ];
    $countries = is_array($config['countries'] ?? null) ? $config['countries'] : [];

    $overrideJson = setting('deposit_protection_overrides', '');
    if ($overrideJson !== '') {
        $overrides = json_decode($overrideJson, true);
        if (is_array($overrides)) {
            foreach ($overrides as $key => $value) {
                if (is_array($value)) {
                    $countries[strtolower((string) $key)] = array_merge($countries[strtolower((string) $key)] ?? [], $value);
                }
            }
        }
    }

    $country = strtolower(trim((string) (($account['country'] ?? '') ?: ($user['country'] ?? ''))));
    $region = user_banking_region($user, $account);
    foreach ($countries as $key => $item) {
        $aliases = array_map('strtolower', array_merge([(string) $key], $item['aliases'] ?? []));
        $matchesProfileCountry = $country !== '' && in_array($country, $aliases, true);
        $matchesFallbackRegion = $country === '' && $region === strtolower((string) $key);
        if ($matchesProfileCountry || $matchesFallbackRegion) {
            return array_merge($default, $item, ['key' => (string) $key]);
        }
    }

    return array_merge($default, ['key' => 'default']);
}

function deposit_protection_badge(array $user, ?array $account = null, string $className = ''): string
{
    $protection = deposit_protection_config_for_user($user, $account);
    $classes = trim('deposit-protection-badge deposit-protection-compact ' . $className);
    return '<section class="' . e($classes) . '" aria-label="Deposit protection information">'
        . '<div class="deposit-protection-mark"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i><span class="deposit-protection-agency">' . e((string) $protection['agency']) . '</span></div>'
        . '<div class="deposit-protection-copy"><strong>' . e((string) $protection['name']) . '</strong><p>' . e((string) $protection['text']) . '</p></div>'
        . '</section>';
}

function account_display_status(array $user): array
{
    $status = strtolower((string) ($user['status'] ?? 'active'));
    $verification = strtolower((string) ($user['verification_status'] ?? 'not_started'));
    $risk = strtolower((string) ($user['risk_status'] ?? 'clear'));

    if ($status === 'frozen') {
        return [
            'label' => 'Frozen',
            'title' => 'Account Frozen',
            'description' => 'Account activity is temporarily restricted. Contact support for review.',
            'class' => 'status-warning',
            'tone' => 'warning',
            'icon' => 'fa-snowflake',
        ];
    }

    if ($status === 'suspended') {
        return [
            'label' => 'Suspended',
            'title' => 'Account Suspended',
            'description' => 'Online banking access is restricted until support completes a review.',
            'class' => 'status-danger',
            'tone' => 'danger',
            'icon' => 'fa-ban',
        ];
    }

    if (in_array($risk, ['fraud_review', 'transfer_restricted'], true)) {
        return [
            'label' => 'Restricted',
            'title' => 'Account Restricted',
            'description' => 'Some account actions are paused while the security review is active.',
            'class' => 'status-danger',
            'tone' => 'danger',
            'icon' => 'fa-shield-halved',
        ];
    }

    if ($verification === 'reupload_requested') {
        return [
            'label' => 'Awaiting Verification',
            'title' => 'Awaiting Verification',
            'description' => 'Updated identity documents are needed before all account features are available.',
            'class' => 'status-warning',
            'tone' => 'warning',
            'icon' => 'fa-id-card',
        ];
    }

    if ($verification !== 'approved' || $risk === 'verification_review') {
        return [
            'label' => 'Pending Approval',
            'title' => 'Pending Admin Approval',
            'description' => 'Your account is being reviewed by operations before full access is enabled.',
            'class' => 'status-info',
            'tone' => 'info',
            'icon' => 'fa-clock',
        ];
    }

    return [
        'label' => 'Active',
        'title' => 'Active',
        'description' => 'Your account is approved and online banking features are available.',
        'class' => 'status-success',
        'tone' => 'success',
        'icon' => 'fa-circle-check',
    ];
}

function google_translate_widget(): string
{
    return '<label class="translate-widget" aria-label="Language selector"><i class="fa-solid fa-language" aria-hidden="true"></i><select class="language-select" data-language-select><option value="en">English</option><option value="fr">French</option><option value="es">Spanish</option><option value="it">Italian</option><option value="pt">Portuguese</option><option value="nl">Dutch</option><option value="tr">Turkish</option><option value="ar">Arabic</option><option value="hi">Hindi</option><option value="zh-CN">Chinese</option></select></label>';
}

function google_translate_script(): string
{
    $defaultLanguage = 'en';
    $forceLanguage = !empty($GLOBALS['forcePageLanguage']) ? 'true' : 'false';

    return str_replace(['__DEFAULT_LANGUAGE__', '__FORCE_LANGUAGE__'], [e($defaultLanguage), $forceLanguage], <<<'HTML'
<div id="google_translate_element" class="google-translate-engine" aria-hidden="true"></div>
<script>
window.LEAD_BANK_DEFAULT_LANGUAGE = '__DEFAULT_LANGUAGE__';
window.LEAD_BANK_FORCE_LANGUAGE = __FORCE_LANGUAGE__;

function googleTranslateElementInit() {
    new google.translate.TranslateElement({
        pageLanguage: 'en',
        includedLanguages: 'ar,en,es,fr,hi,it,ja,ko,nl,pt,ru,tr,uk,zh-CN',
        layout: google.translate.TranslateElement.InlineLayout.SIMPLE,
        autoDisplay: false
    }, 'google_translate_element');
}

(function () {
    const defaultLanguage = window.LEAD_BANK_DEFAULT_LANGUAGE || 'en';
    const cookieName = 'googtrans';
    const promptKey = 'leadBankLanguagePromptSeen';
    const names = {
        en: 'English',
        fr: 'French',
        es: 'Spanish',
        it: 'Italian',
        pt: 'Portuguese',
        nl: 'Dutch',
        tr: 'Turkish',
        ar: 'Arabic',
        hi: 'Hindi',
        'zh-CN': 'Chinese'
    };

    function readCookie(name) {
        return document.cookie.split('; ').find((row) => row.startsWith(name + '='))?.split('=')[1] || '';
    }

    function activeLanguage() {
        const value = decodeURIComponent(readCookie(cookieName));
        const parts = value.split('/');
        return parts[2] || defaultLanguage;
    }

    function setTranslateCookie(language) {
        const value = language === 'en' ? '/en/en' : '/en/' + language;
        const expires = 'expires=Tue, 19 Jan 2038 03:14:07 GMT';
        document.cookie = cookieName + '=' + encodeURIComponent(value) + ';path=/;' + expires;
        document.cookie = cookieName + '=' + encodeURIComponent(value) + ';path=/;domain=' + location.hostname + ';' + expires;
    }

    function updateSelectors(language) {
        document.querySelectorAll('[data-language-select]').forEach((select) => {
            select.value = language;
        });
    }

    function changeLanguage(language) {
        setTranslateCookie(language);
        localStorage.setItem(promptKey, '1');
        location.reload();
    }

    function buildPrompt() {
        if (localStorage.getItem(promptKey) === '1') {
            return;
        }

        const wrapper = document.createElement('div');
        wrapper.className = 'modal fade language-modal';
        wrapper.id = 'languagePromptModal';
        wrapper.tabIndex = -1;
        wrapper.setAttribute('aria-labelledby', 'languagePromptTitle');
        wrapper.setAttribute('aria-hidden', 'true');
        const title = 'Choose language';
        const body = 'This banking page opens in English. You can choose another language at any time.';
        const keepLabel = 'Keep English';
        const applyLabel = 'Apply language';
        wrapper.innerHTML = `
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title fs-5" id="languagePromptTitle">${title}</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="muted mb-3">${body}</p>
                        <select class="form-select" data-language-prompt-select>
                            ${Object.entries(names).map(([code, label]) => `<option value="${code}">${label}</option>`).join('')}
                        </select>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light border" data-bs-dismiss="modal" data-language-keep>${keepLabel}</button>
                        <button type="button" class="btn btn-navy" data-language-apply>${applyLabel}</button>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(wrapper);

        const select = wrapper.querySelector('[data-language-prompt-select]');
        select.value = activeLanguage();
        wrapper.querySelector('[data-language-apply]').addEventListener('click', () => changeLanguage(select.value));
        wrapper.querySelector('[data-language-keep]').addEventListener('click', () => localStorage.setItem(promptKey, '1'));
        wrapper.addEventListener('hidden.bs.modal', () => localStorage.setItem(promptKey, '1'), { once: true });

        if (window.bootstrap?.Modal) {
            window.bootstrap.Modal.getOrCreateInstance(wrapper).show();
        }
    }

    const pageDefaultKey = 'leadBankDefaultLanguage:' + location.pathname;
    if (window.LEAD_BANK_FORCE_LANGUAGE || sessionStorage.getItem(pageDefaultKey) !== '1') {
        setTranslateCookie(defaultLanguage);
        sessionStorage.setItem(pageDefaultKey, '1');
    }

    document.addEventListener('DOMContentLoaded', () => {
        const language = activeLanguage();
        updateSelectors(language);
        document.querySelectorAll('[data-language-select]').forEach((select) => {
            select.addEventListener('change', () => changeLanguage(select.value));
        });
        buildPrompt();
    });
})();
</script>
<script src="https://translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>
HTML);
}
