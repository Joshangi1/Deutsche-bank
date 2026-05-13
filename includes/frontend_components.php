<?php
declare(strict_types=1);

const UI_BRAND_NAME = 'Deutsche';
const UI_BRAND_SHORT = 'Deutsche';

function lead_logo(string $tone = 'dark'): string
{
    $toneClass = $tone === 'light' ? 'brand-mark-light' : 'brand-mark-dark';
    return '<span class="brand-mark ' . $toneClass . '"><span class="brand-symbol" aria-hidden="true"><span></span></span><span>' . e(UI_BRAND_NAME) . '</span></span>';
}

function lead_nav_item(string $href, string $icon, string $label, bool $active = false, bool $disabled = false): string
{
    $classes = 'nav-link' . ($active ? ' active' : '') . ($disabled ? ' disabled restricted-link' : '');
    $url = $disabled ? '#' : url($href);
    $attrs = $disabled ? ' aria-disabled="true" title="Unavailable while account access is restricted"' : '';
    $lock = $disabled ? '<span class="ms-auto"><i class="fa-solid fa-lock"></i></span>' : '';
    return '<a class="' . e($classes) . '" href="' . e($url) . '"' . $attrs . '><i class="fa-solid ' . e($icon) . '"></i><span>' . e($label) . '</span>' . $lock . '</a>';
}

function google_translate_widget(): string
{
    return '<label class="translate-widget" aria-label="Language selector"><i class="fa-solid fa-language" aria-hidden="true"></i><select class="language-select" data-language-select><option value="de">Deutsch</option><option value="en">English</option><option value="fr">Francais</option><option value="es">Espanol</option><option value="it">Italiano</option><option value="pt">Portugues</option><option value="nl">Nederlands</option><option value="tr">Turkce</option><option value="ar">Arabic</option><option value="hi">Hindi</option><option value="zh-CN">Chinese</option></select></label>';
}

function google_translate_script(): string
{
    $defaultLanguage = $GLOBALS['pageLanguage'] ?? 'de';
    $defaultLanguage = preg_match('/^[A-Za-z-]+$/', (string) $defaultLanguage) ? (string) $defaultLanguage : 'de';
    $forceLanguage = !empty($GLOBALS['forcePageLanguage']) ? 'true' : 'false';

    return str_replace(['__DEFAULT_LANGUAGE__', '__FORCE_LANGUAGE__'], [e($defaultLanguage), $forceLanguage], <<<'HTML'
<div id="google_translate_element" class="google-translate-engine" aria-hidden="true"></div>
<script>
window.DEUTSCHE_DEFAULT_LANGUAGE = '__DEFAULT_LANGUAGE__';
window.DEUTSCHE_FORCE_LANGUAGE = __FORCE_LANGUAGE__;

function googleTranslateElementInit() {
    new google.translate.TranslateElement({
        pageLanguage: 'en',
        includedLanguages: 'ar,de,en,es,fr,hi,it,ja,ko,nl,pt,ru,tr,uk,zh-CN',
        layout: google.translate.TranslateElement.InlineLayout.SIMPLE,
        autoDisplay: false
    }, 'google_translate_element');
}

(function () {
    const defaultLanguage = window.DEUTSCHE_DEFAULT_LANGUAGE || 'de';
    const cookieName = 'googtrans';
    const promptKey = 'deutscheLanguagePromptSeen';
    const names = {
        de: 'Deutsch',
        en: 'English',
        fr: 'Francais',
        es: 'Espanol',
        it: 'Italiano',
        pt: 'Portugues',
        nl: 'Nederlands',
        tr: 'Turkce',
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
        const isGermanDefault = defaultLanguage === 'de';
        const title = isGermanDefault ? 'Sprache auswaehlen' : 'Choose language';
        const body = isGermanDefault
            ? 'Diese Seite wird automatisch auf Deutsch angezeigt. Sie koennen jederzeit eine andere Sprache waehlen.'
            : 'This U.S. banking page opens in English. You can choose another language at any time.';
        const keepLabel = isGermanDefault ? 'Deutsch behalten' : 'Keep English';
        const applyLabel = isGermanDefault ? 'Sprache anwenden' : 'Apply language';
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

    const pageDefaultKey = 'deutscheDefaultLanguage:' + location.pathname;
    if (window.DEUTSCHE_FORCE_LANGUAGE || sessionStorage.getItem(pageDefaultKey) !== '1') {
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
