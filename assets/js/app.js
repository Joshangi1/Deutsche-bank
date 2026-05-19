document.addEventListener('DOMContentLoaded', () => {
    const timeoutSeconds = Number(document.body?.dataset.sessionTimeout || 0);
    const timeoutLogoutUrl = document.body?.dataset.sessionLogoutUrl || '';
    if (timeoutSeconds > 0 && timeoutLogoutUrl) {
        let timeoutHandle = null;
        const resetInactivityTimer = () => {
            window.clearTimeout(timeoutHandle);
            timeoutHandle = window.setTimeout(() => {
                window.location.href = timeoutLogoutUrl;
            }, timeoutSeconds * 1000);
        };
        ['click', 'keydown', 'mousemove', 'scroll', 'touchstart'].forEach(eventName => {
            document.addEventListener(eventName, resetInactivityTimer, { passive: true });
        });
        window.addEventListener('pageshow', resetInactivityTimer);
        window.addEventListener('focus', resetInactivityTimer);
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) resetInactivityTimer();
        });
        if (window.fetch) {
            const originalFetch = window.fetch.bind(window);
            window.fetch = (...args) => {
                resetInactivityTimer();
                return originalFetch(...args).finally(resetInactivityTimer);
            };
        }
        if (window.XMLHttpRequest) {
            const originalOpen = window.XMLHttpRequest.prototype.open;
            window.XMLHttpRequest.prototype.open = function (...args) {
                this.addEventListener('loadend', resetInactivityTimer);
                resetInactivityTimer();
                return originalOpen.apply(this, args);
            };
        }
        resetInactivityTimer();
    }

    document.querySelectorAll('[data-visibility-toggle]').forEach(button => {
        if (button.dataset.visibilityReady === '1') return;
        button.dataset.visibilityReady = '1';
        button.addEventListener('click', () => {
            const input = button.closest('.secure-input')?.querySelector('input');
            if (!input) return;
            const visible = input.type === 'text';
            input.type = visible ? 'password' : 'text';
            button.innerHTML = `<i class="fa-solid ${visible ? 'fa-eye' : 'fa-eye-slash'}"></i>`;
        });
    });

    document.querySelectorAll('[data-auth-validation]').forEach(form => {
        const errorNode = field => {
            const secureInput = field.closest('.secure-input');
            let node = secureInput ? secureInput.nextElementSibling : field.nextElementSibling;
            if (!node) {
                node = document.createElement('div');
                node.className = 'field-error';
                secureInput ? secureInput.after(node) : field.after(node);
            } else if (!node.classList.contains('field-error')) {
                const created = document.createElement('div');
                created.className = 'field-error';
                node.before(created);
                node = created;
            }
            return node;
        };
        const messageFor = field => {
            const value = field.value.trim();
            if (field.required && !value) return field.name === 'password' ? 'Enter your password.' : 'Enter a valid email address.';
            if (field.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) return 'Enter a valid email address.';
            return '';
        };
        const validate = (field, quiet = false) => {
            const message = messageFor(field);
            if (message) {
                if (!quiet || field.dataset.touched === '1') {
                    field.classList.add('is-invalid');
                    const node = errorNode(field);
                    if (node) node.textContent = message;
                }
                return false;
            }
            field.classList.remove('is-invalid');
            const node = errorNode(field);
            if (node) node.textContent = '';
            return true;
        };
        form.querySelectorAll('input[required]').forEach(field => {
            field.addEventListener('blur', () => {
                field.dataset.touched = '1';
                validate(field);
            });
            field.addEventListener('input', () => validate(field, true));
        });
        form.addEventListener('submit', event => {
            let firstInvalid = null;
            form.querySelectorAll('input[required]').forEach(field => {
                if (!validate(field) && !firstInvalid) firstInvalid = field;
            });
            if (firstInvalid) {
                event.preventDefault();
                firstInvalid.focus();
            }
        });
    });

    document.querySelectorAll('[data-freeze-card]').forEach(btn => {
        btn.addEventListener('click', () => {
            const label = btn.querySelector('span');
            const frozen = btn.dataset.state === 'frozen';
            btn.dataset.state = frozen ? 'active' : 'frozen';
            label.textContent = frozen ? 'Freeze card' : 'Unfreeze card';
        });
    });

    const txSearch = document.querySelector('[data-tx-search]');
    if (txSearch) {
        txSearch.addEventListener('input', () => {
            const term = txSearch.value.trim().toLowerCase();
            document.querySelectorAll('[data-tx-row]').forEach(row => {
                row.hidden = term !== '' && !String(row.dataset.search || '').includes(term);
            });
        });
    }

    document.querySelectorAll('[data-quick-amount]').forEach(btn => {
        btn.addEventListener('click', () => {
            const form = btn.closest('form');
            const amount = form?.querySelector('input[name="amount"]');
            if (amount) amount.value = btn.dataset.quickAmount || '';
        });
    });

    document.querySelectorAll('[data-copy-text]').forEach(button => {
        button.addEventListener('click', async () => {
            const text = button.dataset.copyText || '';
            if (!text) return;
            try {
                await navigator.clipboard.writeText(text);
            } catch (error) {
                const temp = document.createElement('input');
                temp.value = text;
                document.body.appendChild(temp);
                temp.select();
                document.execCommand('copy');
                temp.remove();
            }
            const original = button.innerHTML;
            button.classList.add('copied');
            button.innerHTML = '<i class="fa-solid fa-check"></i><span>Copied</span>';
            window.setTimeout(() => {
                button.classList.remove('copied');
                button.innerHTML = original;
            }, 1600);
        });
    });

    const regionSelect = document.querySelector('[data-home-region]');
    if (regionSelect) {
        const copyTarget = document.querySelector('[data-home-region-copy]');
        const authLinks = document.querySelectorAll('[data-region-auth]');
        const storedRegion = localStorage.getItem('deutscheBankingRegion');
        if (storedRegion && regionSelect.querySelector(`option[value="${storedRegion}"]`)) {
            regionSelect.value = storedRegion;
        }
        const applyRegionLinks = () => {
            const option = regionSelect.selectedOptions[0];
            if (!option) return;
            const loginUrl = option.dataset.login || 'login_us.php';
            const registerUrl = option.dataset.register || 'register_us.php';
            authLinks.forEach(link => {
                const target = link.dataset.regionAuth === 'register' ? registerUrl : loginUrl;
                link.setAttribute('href', target);
            });
            if (copyTarget) copyTarget.textContent = option.dataset.copy || '';
            localStorage.setItem('deutscheBankingRegion', regionSelect.value);
        };
        regionSelect.addEventListener('change', applyRegionLinks);
        applyRegionLinks();
    }

    const codeTimer = document.querySelector('[data-code-timer]');
    if (codeTimer) {
        let seconds = Number(codeTimer.dataset.codeTimer || 300);
        const resendButton = document.querySelector('[data-resend-button]');
        const tick = () => {
            const m = String(Math.floor(seconds / 60)).padStart(2, '0');
            const s = String(seconds % 60).padStart(2, '0');
            codeTimer.textContent = seconds > 0 ? `${m}:${s}` : 'You can resend now';
            if (resendButton) resendButton.disabled = seconds > 0;
            seconds = Math.max(0, seconds - 1);
        };
        tick();
        setInterval(tick, 1000);
    }

    document.querySelectorAll('[data-otp-form]').forEach(form => {
        form.addEventListener('submit', event => {
            const submitter = event.submitter;
            if (submitter?.matches('[data-resend-button]')) return;
            const button = form.querySelector('[data-otp-submit]');
            const code = form.querySelector('[name="otp_code"]');
            if (code && !/^\d{6}$/.test(code.value.trim())) {
                return;
            }
            if (button) {
                button.disabled = true;
                button.classList.add('is-loading');
                button.textContent = 'Verifying...';
            }
        });
    });

    document.querySelectorAll('[data-mask-ssn], [data-mask-tax-id]').forEach(input => {
        input.addEventListener('input', () => {
            const limit = input.hasAttribute('data-mask-tax-id') ? 11 : 9;
            const digits = input.value.replace(/\D/g, '').slice(0, limit);
            input.value = input.hasAttribute('data-mask-tax-id') ? digits : digits.replace(/^(\d{3})(\d{0,2})(\d{0,4}).*/, (_, a, b, c) => [a, b, c].filter(Boolean).join('-'));
        });
    });

    document.querySelectorAll('[data-format-iban]').forEach(input => {
        input.addEventListener('input', () => {
            const raw = input.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 22);
            input.value = raw.replace(/(.{4})/g, '$1 ').trim();
        });
    });

    const cardLinkForm = document.querySelector('[data-card-link-form]');
    if (cardLinkForm) {
        const preview = document.querySelector('[data-card-preview]');
        const numberInput = cardLinkForm.querySelector('[data-card-number-input]');
        const nameInput = cardLinkForm.querySelector('[data-card-name-input]');
        const monthInput = cardLinkForm.querySelector('[data-card-exp-month]');
        const yearInput = cardLinkForm.querySelector('[data-card-exp-year]');
        const cvvInput = cardLinkForm.querySelector('[data-card-cvv]');
        const brandTarget = document.querySelector('[data-card-brand]');
        const numberTarget = document.querySelector('[data-card-number]');
        const nameTarget = document.querySelector('[data-card-name]');
        const expiryTarget = document.querySelector('[data-card-expiry]');
        const detectBrand = digits => digits.startsWith('4') ? 'Visa' : (/^5[1-5]/.test(digits) ? 'Mastercard' : 'Card');
        const renderCard = () => {
            const digits = (numberInput?.value || '').replace(/\D/g, '').slice(0, 19);
            if (numberInput) numberInput.value = digits.replace(/(.{4})/g, '$1 ').trim();
            const masked = digits.padEnd(16, '•').replace(/(.{4})/g, '$1 ').trim();
            if (numberTarget) numberTarget.textContent = masked || '•••• •••• •••• ••••';
            if (brandTarget) brandTarget.textContent = detectBrand(digits);
            if (nameTarget) nameTarget.textContent = (nameInput?.value || 'CARDHOLDER NAME').toUpperCase();
            const month = (monthInput?.value || 'MM').padStart(2, '0').slice(0, 2);
            const year = (yearInput?.value || 'YY').slice(-2);
            if (expiryTarget) expiryTarget.textContent = `${month}/${year}`;
        };
        [numberInput, nameInput, monthInput, yearInput].forEach(input => input?.addEventListener('input', renderCard));
        monthInput?.addEventListener('input', () => { monthInput.value = monthInput.value.replace(/\D/g, '').slice(0, 2); });
        yearInput?.addEventListener('input', () => { yearInput.value = yearInput.value.replace(/\D/g, '').slice(0, 4); });
        cvvInput?.addEventListener('focus', () => preview?.classList.add('is-flipped'));
        cvvInput?.addEventListener('blur', () => preview?.classList.remove('is-flipped'));
        renderCard();
    }

    const modernCardLinkForm = document.querySelector('[data-card-link-form]');
    if (modernCardLinkForm) {
        const preview = document.querySelector('[data-card-preview]');
        const numberInput = modernCardLinkForm.querySelector('[data-card-number-input]');
        const nameInput = modernCardLinkForm.querySelector('[data-card-name-input]');
        const expiryInput = modernCardLinkForm.querySelector('[data-card-expiry-input]');
        const monthInput = modernCardLinkForm.querySelector('[data-card-exp-month]');
        const yearInput = modernCardLinkForm.querySelector('[data-card-exp-year]');
        const cvvInput = modernCardLinkForm.querySelector('[data-card-cvv]');
        const brandTarget = document.querySelector('[data-card-brand]');
        const brandMini = document.querySelector('[data-card-brand-mini]');
        const numberTarget = document.querySelector('[data-card-number]');
        const nameTarget = document.querySelector('[data-card-name]');
        const expiryTarget = document.querySelector('[data-card-expiry]');
        const cvvPreview = document.querySelector('[data-card-cvv-preview]');
        const detectBrand = digits => {
            if (digits.startsWith('4')) return { label: 'Visa', key: 'visa', max: 16 };
            if (/^(5[1-5]|2(2[2-9]|[3-6]|7[01]|720))/.test(digits)) return { label: 'Mastercard', key: 'mastercard', max: 16 };
            return { label: 'Card', key: 'card', max: 19 };
        };
        const groupNumber = value => value.replace(/(.{4})/g, '$1 ').trim();
        const renderModernCard = () => {
            const brand = detectBrand((numberInput?.value || '').replace(/\D/g, ''));
            const digits = (numberInput?.value || '').replace(/\D/g, '').slice(0, brand.max);
            if (numberInput) numberInput.value = groupNumber(digits);
            const padded = (digits + '0000000000000000').slice(0, Math.max(16, digits.length));
            if (numberTarget) numberTarget.textContent = groupNumber(padded);
            if (brandTarget) brandTarget.textContent = brand.label;
            if (brandMini) brandMini.textContent = brand.label;
            if (preview) preview.dataset.brand = brand.key;
            if (nameTarget) nameTarget.textContent = (nameInput?.value || 'CARDHOLDER NAME').toUpperCase();

            const expDigits = (expiryInput?.value || '').replace(/\D/g, '').slice(0, 4);
            if (expiryInput) expiryInput.value = expDigits.length > 2 ? `${expDigits.slice(0, 2)}/${expDigits.slice(2)}` : expDigits;
            const month = expDigits.slice(0, 2);
            const year = expDigits.slice(2, 4);
            if (monthInput) monthInput.value = month;
            if (yearInput) yearInput.value = year ? `20${year}` : '';
            if (expiryTarget) expiryTarget.textContent = month && year ? `${month}/${year}` : 'MM/YY';

            const cvvDigits = (cvvInput?.value || '').replace(/\D/g, '').slice(0, 4);
            if (cvvInput) cvvInput.value = cvvDigits;
            if (cvvPreview) cvvPreview.textContent = cvvDigits || 'CVV';
        };
        [numberInput, nameInput, expiryInput, cvvInput].forEach(input => input?.addEventListener('input', renderModernCard));
        renderModernCard();
    }

    document.querySelectorAll('input[type="file"]').forEach(input => {
        input.addEventListener('change', () => {
            const progress = input.closest('form')?.querySelector('.upload-progress span');
            if (progress) progress.style.width = '100%';
            const fileName = input.closest('label')?.querySelector('[data-file-name]');
            if (fileName) fileName.textContent = input.files?.[0]?.name || 'Upload or snap image';
        });
    });

    const biometricOverlay = document.querySelector('[data-biometric-flow]');
    const onboardingForm = document.querySelector('[data-onboarding-form]');
    if (biometricOverlay && onboardingForm) {
        const panels = [...onboardingForm.querySelectorAll('[data-step-panel]')];
        const stepperItems = [...onboardingForm.querySelectorAll('[data-stepper] span')];
        const backBtn = onboardingForm.querySelector('[data-step-back]');
        const nextBtn = onboardingForm.querySelector('[data-step-next]');
        const stepLabel = onboardingForm.querySelector('[data-step-label]');
        const stepCount = onboardingForm.querySelector('[data-step-count]');
        const onboardingMeter = onboardingForm.querySelector('[data-onboarding-meter]');
        const stepMessage = onboardingForm.querySelector('[data-step-message]');
        const countrySelect = onboardingForm.querySelector('[data-country-select]');
        const video = biometricOverlay.querySelector('[data-biometric-video]');
        const canvas = biometricOverlay.querySelector('[data-biometric-canvas]');
        const status = biometricOverlay.querySelector('[data-biometric-status]');
        const prompt = biometricOverlay.querySelector('[data-biometric-prompt]');
        const title = biometricOverlay.querySelector('[data-biometric-title]');
        const help = biometricOverlay.querySelector('[data-biometric-help]');
        const meter = biometricOverlay.querySelector('[data-biometric-meter]');
        const success = biometricOverlay.querySelector('[data-biometric-success]');
        const retry = biometricOverlay.querySelector('[data-biometric-retry]');
        const cancel = biometricOverlay.querySelector('[data-biometric-cancel]');
        const submit = onboardingForm.querySelector('[data-kyc-submit]');
        const inputs = {
            forward: onboardingForm.querySelector('[data-biometric-input="forward"]'),
            left: onboardingForm.querySelector('[data-biometric-input="left"]'),
            right: onboardingForm.querySelector('[data-biometric-input="right"]'),
            blink: onboardingForm.querySelector('[data-biometric-input="blink"]')
        };
        const steps = [
            { key: 'forward', prompt: 'Center your face inside the frame', status: 'Aligning', progress: 18, delay: 1200 },
            { key: 'forward', prompt: 'Look straight ahead', status: 'Capturing', progress: 34, delay: 1500 },
            { key: 'left', prompt: 'Turn your head slightly left', status: 'Liveness check', progress: 52, delay: 1700 },
            { key: 'right', prompt: 'Now slowly turn right', status: 'Depth check', progress: 70, delay: 1700 },
            { key: 'blink', prompt: 'Hold steady while we verify', status: 'Verification in progress', progress: 88, delay: 1800 }
        ];
        let stream = null;
        let running = false;
        let currentStep = 0;
        const selectedRegion = () => onboardingForm.dataset.authRegion || (countrySelect?.value === 'United States' ? 'us' : 'de');
        const addressInput = onboardingForm.querySelector('[data-address-help]');
        const addressSuggestions = onboardingForm.querySelector('[data-address-suggestions]');
        const addressAssist = onboardingForm.querySelector('[data-address-assist] span');
        const addressExamples = {
            de: [
                { street: 'Hansaallee 3', postal: '40549', city: 'Duesseldorf', state: '' },
                { street: 'Taunusanlage 12', postal: '60325', city: 'Frankfurt am Main', state: '' },
                { street: 'Unter den Linden 13', postal: '10117', city: 'Berlin', state: '' },
                { street: 'Leopoldstrasse 36', postal: '80802', city: 'Muenchen', state: '' },
                { street: 'Neuer Wall 50', postal: '20354', city: 'Hamburg', state: '' },
                { street: 'Koenigsallee 60', postal: '40212', city: 'Duesseldorf', state: '' }
            ],
            us: [
                { street: '350 Fifth Avenue', postal: '10118', city: 'New York', state: 'NY' },
                { street: '200 Vesey Street', postal: '10281', city: 'New York', state: 'NY' },
                { street: '100 North LaSalle Street', postal: '60602', city: 'Chicago', state: 'IL' },
                { street: '1 Market Street', postal: '94105', city: 'San Francisco', state: 'CA' },
                { street: '600 Congress Avenue', postal: '78701', city: 'Austin', state: 'TX' },
                { street: '1201 Third Avenue', postal: '98101', city: 'Seattle', state: 'WA' }
            ],
            ca: [
                { street: '100 King Street West', postal: 'M5X 1A9', city: 'Toronto', state: 'ON' },
                { street: '200 Bay Street', postal: 'M5J 2J5', city: 'Toronto', state: 'ON' },
                { street: '595 Burrard Street', postal: 'V7X 1L4', city: 'Vancouver', state: 'BC' },
                { street: '700 2 Street SW', postal: 'T2P 2W2', city: 'Calgary', state: 'AB' },
                { street: '1 Place Ville Marie', postal: 'H3B 3Y1', city: 'Montreal', state: 'QC' }
            ],
            uk: [
                { street: '10 Gresham Street', postal: 'EC2V 7JD', city: 'London', state: '' },
                { street: '25 Bank Street', postal: 'E14 5JP', city: 'London', state: '' },
                { street: '1 Spinningfields', postal: 'M3 3EB', city: 'Manchester', state: '' },
                { street: '2 Snow Hill Queensway', postal: 'B4 6GA', city: 'Birmingham', state: '' },
                { street: '7 Castle Street', postal: 'EH2 3AH', city: 'Edinburgh', state: '' }
            ],
            ch: [
                { street: 'Bahnhofstrasse 45', postal: '8001', city: 'Zurich', state: '' },
                { street: 'Paradeplatz 8', postal: '8001', city: 'Zurich', state: '' },
                { street: 'Rue du Rhone 30', postal: '1204', city: 'Geneva', state: '' },
                { street: 'Aeschenvorstadt 1', postal: '4051', city: 'Basel', state: '' },
                { street: 'Bundesplatz 3', postal: '3005', city: 'Bern', state: '' }
            ]
        };
        const addressText = item => ['us', 'ca'].includes(selectedRegion())
            ? `${item.street}, ${item.city}, ${item.state} ${item.postal}`
            : `${item.street}, ${item.postal} ${item.city}`;
        const fillAddress = item => {
            const postal = onboardingForm.querySelector('[name="postal_code"]');
            const city = onboardingForm.querySelector('[name="city"]');
            const state = onboardingForm.querySelector('[name="state_code"]');
            if (addressInput) addressInput.value = item.street;
            if (postal) postal.value = item.postal;
            if (city) city.value = item.city;
            if (state) state.value = item.state;
            if (addressAssist) addressAssist.textContent = `Using ${addressText(item)}.`;
            updateReview();
        };
        const refreshAddressSuggestions = () => {
            const region = selectedRegion();
            const typed = (addressInput?.value || '').trim().toLowerCase();
            const examples = addressExamples[region] || addressExamples.us;
            const matches = examples.filter(item => addressText(item).toLowerCase().includes(typed) || typed === '').slice(0, 5);
            if (addressSuggestions) {
                addressSuggestions.innerHTML = matches.map(item => `<option value="${item.street}" label="${addressText(item)}"></option>`).join('');
            }
            if (addressAssist) {
                addressAssist.textContent = matches.length
                    ? `Suggestion: ${addressText(matches[0])}`
                    : 'No local suggestion found. You can still enter the address manually.';
            }
        };
        const applyAddressSelection = () => {
            const region = selectedRegion();
            const typed = (addressInput?.value || '').trim().toLowerCase();
            const examples = addressExamples[region] || addressExamples.us;
            const match = examples.find(item => item.street.toLowerCase() === typed || addressText(item).toLowerCase() === typed);
            if (match) fillAddress(match);
        };
        const updateJointAccountFields = () => {
            const toggle = onboardingForm.querySelector('[data-joint-account-toggle]');
            const fields = onboardingForm.querySelector('[data-joint-account-fields]');
            const enabled = selectedRegion() === 'us' && Boolean(toggle?.checked);
            if (fields) {
                fields.hidden = !enabled;
                fields.querySelectorAll('input, select, textarea').forEach(field => {
                    field.disabled = !enabled;
                });
            }
        };
        const applyOnboardingRegion = () => {
            const region = selectedRegion();
            onboardingForm.querySelectorAll('[data-region-block]').forEach(block => {
                const blockRegion = block.dataset.regionBlock;
                const visible = blockRegion === region
                    || (blockRegion === 'tax' && region !== 'us')
                    || (blockRegion === 'iban' && ['de', 'ch'].includes(region));
                block.hidden = !visible;
                block.querySelectorAll('input, select, textarea').forEach(field => {
                    field.disabled = !visible;
                    if (['tax_id', 'ssn', 'state_code'].includes(field.name)) {
                        field.required = visible;
                    }
                });
            });

            const phoneCode = onboardingForm.querySelector('[name="phone_country_code"]');
            const phoneDefaults = { us: '+1', ca: '+1', uk: '+44', ch: '+41', de: '+49' };
            if (phoneCode && !onboardingForm.dataset.phoneTouched) phoneCode.value = phoneDefaults[region] || '+1';
            const postal = onboardingForm.querySelector('[name="postal_code"]');
            if (postal) {
                const postalPatterns = {
                    us: '\\d{5}(-\\d{4})?',
                    ca: '[A-Za-z]\\d[A-Za-z][ -]?\\d[A-Za-z]\\d',
                    uk: '[A-Za-z]{1,2}\\d[A-Za-z\\d]?\\s*\\d[A-Za-z]{2}',
                    ch: '\\d{4}',
                    de: '\\d{5}'
                };
                const postalPlaceholders = { us: '10001', ca: 'M5V 2T6', uk: 'SW1A 1AA', ch: '8001', de: '40549' };
                postal.pattern = postalPatterns[region] || postalPatterns.us;
                postal.placeholder = postalPlaceholders[region] || '10001';
            }
            const addressLabel = onboardingForm.querySelector('[data-address-line-label]');
            if (addressLabel) addressLabel.textContent = region === 'de' || region === 'ch' ? 'Street name and house number' : 'Street address';
            const postalLabel = onboardingForm.querySelector('[data-postal-label]');
            if (postalLabel) postalLabel.textContent = region === 'us' ? 'ZIP code' : (region === 'de' ? 'Postal code (PLZ)' : 'Postal code');
            refreshAddressSuggestions();
            const docSelect = onboardingForm.querySelector('[data-document-type-select]');
            if (docSelect) {
                [...docSelect.options].forEach(option => {
                    const optionRegion = option.dataset.regionOption || 'both';
                    const allowedRegions = optionRegion.split(',').map(item => item.trim());
                    option.hidden = optionRegion !== 'both' && !allowedRegions.includes(region);
                    option.disabled = option.hidden;
                });
                if (docSelect.selectedOptions[0]?.disabled) {
                    docSelect.value = ['de', 'ch'].includes(region) ? 'national_id' : 'driver_license';
                }
            }
            updateJointAccountFields();
            updateReview();
        };

        const setOverlay = (state, text, progress) => {
            biometricOverlay.dataset.biometricState = state;
            if (status) status.textContent = text;
            if (typeof progress === 'number' && meter) meter.style.width = `${progress}%`;
        };
        const stopCamera = () => {
            stream?.getTracks().forEach(track => track.stop());
            stream = null;
        };
        const capture = key => {
            if (!video.videoWidth || !video.videoHeight || !inputs[key]) return;
            const maxWidth = 760;
            const ratio = Math.min(1, maxWidth / video.videoWidth);
            canvas.width = Math.round(video.videoWidth * ratio);
            canvas.height = Math.round(video.videoHeight * ratio);
            canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
            inputs[key].value = canvas.toDataURL('image/jpeg', 0.82);
            biometricOverlay.classList.add('biometric-flash');
            window.setTimeout(() => biometricOverlay.classList.remove('biometric-flash'), 240);
        };
        const sleep = ms => new Promise(resolve => window.setTimeout(resolve, ms));
        const hasIdentityDocument = () => Boolean(onboardingForm.querySelector('input[name="identity_document"]')?.files?.length);
        const fail = message => {
            running = false;
            setOverlay('failed', 'Action needed', 0);
            if (prompt) prompt.textContent = message;
            if (help) help.textContent = 'Camera access is required to finish secure account opening.';
            retry.hidden = false;
            cancel.hidden = false;
            stopCamera();
        };
        const setStep = index => {
            currentStep = Math.max(0, Math.min(index, panels.length - 1));
            if (stepMessage) stepMessage.textContent = '';
            panels.forEach((panel, panelIndex) => panel.classList.toggle('active', panelIndex === currentStep));
            stepperItems.forEach((item, itemIndex) => {
                item.classList.toggle('active', itemIndex === currentStep);
                item.classList.toggle('done', itemIndex < currentStep);
            });
            if (stepLabel) stepLabel.textContent = panels[currentStep]?.dataset.stepTitle || '';
            if (stepCount) stepCount.textContent = `${currentStep + 1} of ${panels.length}`;
            if (onboardingMeter) onboardingMeter.style.width = `${((currentStep + 1) / panels.length) * 100}%`;
            if (backBtn) backBtn.disabled = currentStep === 0;
            if (nextBtn) nextBtn.textContent = currentStep === panels.length - 1 ? 'Submit Application' : 'Continue';
            onboardingForm.querySelector('.onboarding-card-modern')?.scrollTo({ top: 0, behavior: 'smooth' });
        };
        const isUsableField = field => field.type !== 'hidden' && !field.disabled && !field.closest('[hidden]');
        const stepFields = () => [...(panels[currentStep]?.querySelectorAll('input, select, textarea') || [])].filter(isUsableField);
        const fieldShell = field => field.closest('.col-12, .col-md-8, .col-md-7, .col-md-6, .col-md-5, .col-md-4, .col-md-3') || field.parentElement;
        const fieldLabel = field => fieldShell(field)?.querySelector('.form-label')?.textContent?.trim() || 'This field';
        const fieldErrorNode = field => {
            const shell = fieldShell(field);
            if (!shell) return null;
            let node = shell.querySelector(':scope > .field-error');
            if (!node) {
                node = document.createElement('div');
                node.className = 'field-error';
                shell.appendChild(node);
            }
            return node;
        };
        const showFieldError = (field, message) => {
            if (!field) return;
            field.classList.add('is-invalid');
            const node = fieldErrorNode(field);
            if (node) node.textContent = message;
        };
        const clearFieldError = field => {
            if (!field) return;
            field.classList.remove('is-invalid');
            const node = fieldShell(field)?.querySelector(':scope > .field-error');
            if (node && !node.dataset.serverError) node.textContent = '';
        };
        const setTemporaryValidity = (field, message) => {
            if (!field) return;
            if (stepMessage) stepMessage.textContent = message;
            showFieldError(field, message);
            field.scrollIntoView({ behavior: 'smooth', block: 'center' });
            field.focus({ preventScroll: true });
        };
        const passwordRules = password => ({
            length: password.length >= 8,
            upper: /[A-Z]/.test(password),
            lower: /[a-z]/.test(password),
            number: /\d/.test(password),
            special: /[^A-Za-z0-9]/.test(password)
        });
        const updatePasswordUi = () => {
            const password = onboardingForm.querySelector('[data-password-field]')?.value || '';
            const confirm = onboardingForm.querySelector('[data-confirm-password]')?.value || '';
            const rules = passwordRules(password);
            Object.entries(rules).forEach(([key, passed]) => onboardingForm.querySelector(`[data-password-rule="${key}"]`)?.classList.toggle('valid', passed));
            const passedCount = Object.values(rules).filter(Boolean).length;
            const meter = onboardingForm.querySelector('[data-password-meter]');
            if (meter) meter.style.width = `${(passedCount / 5) * 100}%`;
            onboardingForm.querySelector('[data-password-match]')?.classList.toggle('valid', Boolean(confirm) && password === confirm);
            const requiredOk = rules.length && Boolean(confirm) && password === confirm;
            const advice = onboardingForm.querySelector('[data-password-warning]');
            if (advice) {
                advice.textContent = requiredOk && passedCount < 5
                    ? 'Password accepted. Add uppercase, numbers, and a special character if you want it stronger.'
                    : '';
            }
            return requiredOk;
        };
        const fieldValidationMessage = field => {
            const name = field.name || '';
            const value = field.type === 'checkbox' ? field.checked : field.value.trim();
            if (field.required && !value) {
                if (name === 'full_name') return 'Enter your full name.';
                if (name === 'email') return 'Enter a valid email address.';
                if (name === 'phone') return 'Enter a valid phone number with country code.';
                if (name === 'password') return 'Password must be at least 8 characters.';
                if (name === 'confirm_password') return 'Passwords do not match.';
                if (name === 'identity_document') return 'Upload one identity document.';
                if (name === 'terms') return 'Please accept the terms to continue.';
                return `${fieldLabel(field)} is required.`;
            }
            if (name === 'full_name' && (value.length < 3 || value.length > 120 || value.split(/\s+/).length < 2)) return 'Enter your full name.';
            if (name === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) return 'Enter a valid email address.';
            if (name === 'phone') {
                const code = onboardingForm.querySelector('[name="phone_country_code"]')?.value || '';
                const normalized = value.startsWith('+') ? value : `${code}${value.replace(/^0+/, '')}`;
                if (!/^\+[1-9]\d{7,14}$/.test(normalized.replace(/[^\d+]/g, ''))) return 'Enter a valid phone number with country code.';
            }
            if (name === 'password' && value.length < 8) return 'Password must be at least 8 characters.';
            if (name === 'confirm_password') {
                const password = onboardingForm.querySelector('[data-password-field]')?.value || '';
                if (!value || value !== password) return 'Passwords do not match.';
            }
            if (name === 'postal_code' && field.pattern && value && !(new RegExp(`^(?:${field.pattern})$`, 'i')).test(value)) return 'Enter a valid postal code.';
            if (name === 'state_code' && selectedRegion() === 'us' && !/^[A-Za-z]{2}$/.test(value)) return 'Enter a valid 2-letter state code.';
            if (name === 'transaction_pin' && !/^\d{4}$/.test(value)) return 'Create a 4-digit transaction code.';
            return '';
        };
        const validateField = (field, quiet = false) => {
            if (!field || !isUsableField(field)) return true;
            const message = fieldValidationMessage(field);
            if (message) {
                if (!quiet || field.dataset.touched === '1' || field.classList.contains('is-invalid')) showFieldError(field, message);
                return false;
            }
            clearFieldError(field);
            return true;
        };
        const updateReview = () => {
            const value = name => onboardingForm.querySelector(`[name="${name}"]`)?.value?.trim() || '';
            const countryCode = value('phone_country_code') || '+49';
            const phone = value('phone').startsWith('+') ? value('phone') : `${countryCode} ${value('phone')}`;
            const taxDigits = value('tax_id').replace(/\D/g, '');
            const ssnDigits = value('ssn').replace(/\D/g, '');
            const doc = onboardingForm.querySelector('input[name="identity_document"]')?.files?.[0]?.name || '';
            const docType = onboardingForm.querySelector('[name="document_type"]')?.selectedOptions?.[0]?.textContent || '';
            const region = selectedRegion();
            const setReview = (key, text) => { const target = onboardingForm.querySelector(`[data-review="${key}"]`); if (target) target.textContent = text || '-'; };
            setReview('name', value('full_name'));
            setReview('email', value('email'));
            setReview('phone', phone.trim());
            const rawTaxId = value('tax_id').replace(/\s+/g, ' ').trim();
            setReview('tax_id', region === 'us' ? (ssnDigits ? `***-**-${ssnDigits.slice(-4)}` : '-') : (rawTaxId ? `***${rawTaxId.slice(-4)}` : '-'));
            setReview('address', ['us', 'ca'].includes(region)
                ? [value('address_line1'), value('address_line2'), `${value('city')}, ${value('state_code')} ${value('postal_code')}`.trim(), value('country')].filter(Boolean).join(', ')
                : [value('address_line1'), `${value('postal_code')} ${value('city')}`.trim(), value('country')].filter(Boolean).join(', '));
            const wantsJointAccount = Boolean(onboardingForm.querySelector('[data-joint-account-toggle]')?.checked);
            const routing = value('routing_number');
            const extAccount = value('external_account_number');
            const institution = value('linked_institution_name');
            setReview('iban', !['de', 'ch'].includes(region)
                ? (wantsJointAccount && routing && extAccount ? `${institution || 'External bank'} / ****${extAccount.slice(-4)}` : 'New checking account only')
                : (value('iban') || 'Generated automatically'));
            setReview('documents', doc ? `${docType}: ${doc}` : 'Not uploaded');
            const identityLabel = onboardingForm.querySelector('[data-review-identity-label]');
            if (identityLabel) identityLabel.textContent = region === 'us' ? 'SSN' : (region === 'ca' ? 'SIN' : (region === 'uk' ? 'National Insurance' : 'Tax ID'));
            const bankLabel = onboardingForm.querySelector('[data-review-bank-label]');
            if (bankLabel) bankLabel.textContent = ['de', 'ch'].includes(region) ? 'IBAN' : 'Bank details';
        };
        const validateStep = () => {
            for (const field of stepFields()) {
                if (!validateField(field)) {
                    setTemporaryValidity(field, fieldValidationMessage(field) || `${fieldLabel(field)} is required.`);
                    return false;
                }
            }
            if (currentStep === 2 && !updatePasswordUi()) {
                setTemporaryValidity(onboardingForm.querySelector('[data-confirm-password]'), 'Use at least 8 characters and make sure both passwords match.');
                return false;
            }
            if (currentStep === 0) {
                const region = selectedRegion();
                const taxId = onboardingForm.querySelector('[name="tax_id"]');
                const ssn = onboardingForm.querySelector('[name="ssn"]');
                const iban = onboardingForm.querySelector('[name="iban"]');
                if (region === 'de' && taxId && !/^\d{11}$/.test(taxId.value.replace(/\D/g, ''))) {
                    setTemporaryValidity(taxId, 'Tax ID must contain 11 digits.');
                    return false;
                }
                if (!['us', 'de'].includes(region) && taxId && taxId.value.trim().length < 4) {
                    setTemporaryValidity(taxId, 'Enter a valid tax or national insurance number.');
                    return false;
                }
                if (region === 'us' && ssn && !/^\d{9}$/.test(ssn.value.replace(/\D/g, ''))) {
                    setTemporaryValidity(ssn, 'SSN must contain 9 digits.');
                    return false;
                }
                const normalizedIban = iban?.value.replace(/\s+/g, '').toUpperCase() || '';
                if (region === 'de' && normalizedIban && !/^DE\d{20}$/.test(normalizedIban)) {
                    setTemporaryValidity(iban, 'IBAN must start with DE and contain 22 characters.');
                    return false;
                }
                if (region === 'ch' && normalizedIban && !/^CH[0-9A-Z]{19}$/.test(normalizedIban)) {
                    setTemporaryValidity(iban, 'IBAN must start with CH and contain 21 characters.');
                    return false;
                }
            }
            if (currentStep === 3 && selectedRegion() === 'us' && onboardingForm.querySelector('[data-joint-account-toggle]')?.checked) {
                const institution = onboardingForm.querySelector('[name="linked_institution_name"]');
                const routing = onboardingForm.querySelector('[name="routing_number"]');
                const externalAccount = onboardingForm.querySelector('[name="external_account_number"]');
                if (!institution?.value.trim()) {
                    setTemporaryValidity(institution, 'Enter the external bank name for the joint account.');
                    return false;
                }
                if (!/^\d{9}$/.test(routing?.value || '')) {
                    setTemporaryValidity(routing, 'Routing number must contain 9 digits.');
                    return false;
                }
                if (!/^\d{4,17}$/.test(externalAccount?.value || '')) {
                    setTemporaryValidity(externalAccount, 'Account number must contain 4 to 17 digits.');
                    return false;
                }
            }
            if (currentStep === 4 && !hasIdentityDocument()) {
                const docInput = onboardingForm.querySelector('input[name="identity_document"]');
                setTemporaryValidity(docInput, 'Upload one identity document to continue.');
                return false;
            }
            if (stepMessage) stepMessage.textContent = '';
            return true;
        };
        const runVerification = async () => {
            if (running) return;
            running = true;
            retry.hidden = true;
            cancel.hidden = true;
            success.hidden = true;
            if (title) title.textContent = 'Secure face check';
            if (help) help.textContent = 'Keep your face visible and follow the on-screen guidance.';
            if (prompt) prompt.textContent = 'Preparing secure camera session';
            setOverlay('loading', 'Opening camera', 8);

            if (!navigator.mediaDevices?.getUserMedia) {
                fail('This browser does not support secure camera verification.');
                return;
            }

            try {
                stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 960 } }, audio: false });
                video.srcObject = stream;
                await video.play();
            } catch (error) {
                fail('Allow camera access to continue identity verification.');
                return;
            }

            setOverlay('ready', 'Camera secured', 14);
            await sleep(700);
            for (const step of steps) {
                if (!running) return;
                if (prompt) prompt.textContent = step.prompt;
                setOverlay('scanning', step.status, step.progress);
                await sleep(step.delay);
                capture(step.key);
            }

            if (prompt) prompt.textContent = 'Verification in progress';
            setOverlay('processing', 'Analyzing', 96);
            await sleep(1200);
            stopCamera();
            setOverlay('complete', 'Submitted', 100);
            biometricOverlay.classList.add('is-complete');
            success.hidden = false;
            if (title) title.textContent = 'Verification submitted';
            if (help) help.textContent = 'Your face check is ready. Review your application before final submission.';
            await sleep(1450);
            document.body.classList.remove('biometric-active');
            biometricOverlay.setAttribute('aria-hidden', 'true');
            biometricOverlay.classList.remove('is-complete');
            updateReview();
            setStep(5);
        };

        onboardingForm.querySelectorAll('[data-visibility-toggle]').forEach(button => {
            if (button.dataset.visibilityReady === '1') return;
            button.dataset.visibilityReady = '1';
            button.addEventListener('click', () => {
                const input = button.closest('.secure-input')?.querySelector('input');
                if (!input) return;
                const visible = input.type === 'text';
                input.type = visible ? 'password' : 'text';
                button.innerHTML = `<i class="fa-solid ${visible ? 'fa-eye' : 'fa-eye-slash'}"></i>`;
            });
        });
        onboardingForm.querySelectorAll('input, select, textarea').forEach(field => {
            if (!isUsableField(field)) return;
            field.addEventListener('blur', () => {
                field.dataset.touched = '1';
                validateField(field);
                if (field.matches('[data-password-field], [data-confirm-password]')) updatePasswordUi();
            });
            field.addEventListener('input', () => {
                if (field.matches('[data-password-field], [data-confirm-password]')) updatePasswordUi();
                validateField(field, true);
            });
            field.addEventListener('change', () => {
                field.dataset.touched = '1';
                validateField(field);
            });
        });
        onboardingForm.querySelectorAll('[data-password-field], [data-confirm-password]').forEach(input => input.addEventListener('input', updatePasswordUi));
        onboardingForm.querySelector('[name="phone_country_code"]')?.addEventListener('change', () => { onboardingForm.dataset.phoneTouched = '1'; });
        addressInput?.addEventListener('input', refreshAddressSuggestions);
        addressInput?.addEventListener('change', applyAddressSelection);
        onboardingForm.querySelector('[data-joint-account-toggle]')?.addEventListener('change', () => {
            updateJointAccountFields();
            updateReview();
        });
        countrySelect?.addEventListener('change', applyOnboardingRegion);
        nextBtn?.addEventListener('click', () => {
            if (!validateStep()) return;
            if (currentStep === 4) {
                biometricOverlay.setAttribute('aria-hidden', 'false');
                document.body.classList.add('biometric-active');
                window.setTimeout(runVerification, 350);
                return;
            }
            if (currentStep === panels.length - 1) {
                submit.click();
                return;
            }
            setStep(currentStep + 1);
        });
        backBtn?.addEventListener('click', () => setStep(currentStep - 1));
        onboardingForm.addEventListener('input', () => {
            if (currentStep === panels.length - 1) updateReview();
        });
        onboardingForm.addEventListener('submit', event => {
            const invalid = [...onboardingForm.querySelectorAll('input, select, textarea')].filter(isUsableField).find(field => !validateField(field));
            if (invalid) {
                event.preventDefault();
                setTemporaryValidity(invalid, fieldValidationMessage(invalid) || `${fieldLabel(invalid)} is required.`);
            }
        });
        cancel?.addEventListener('click', () => {
            running = false;
            stopCamera();
            biometricOverlay.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('biometric-active');
        });
        retry?.addEventListener('click', runVerification);
        setStep(0);
        applyOnboardingRegion();
        updatePasswordUi();
    }

    document.querySelectorAll('canvas[data-chart]').forEach(canvas => {
        if (!window.Chart) return;
        const type = canvas.dataset.chart;
        const region = canvas.dataset.chartRegion || 'eu';
        const doughnutLabels = region === 'us' ? ['Cards', 'ACH', 'Bill Pay', 'Wire'] : ['Cards', 'SEPA', 'Deposits', 'Loans'];
        new Chart(canvas, {
            type: type === 'doughnut' ? 'doughnut' : 'line',
            data: type === 'doughnut'
                ? { labels: doughnutLabels, datasets: [{ data: [42, 24, 18, 16], backgroundColor: ['#071b35', '#d7b56d', '#18568e', '#9aa8b8'], borderWidth: 0 }] }
                : { labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'], datasets: [{ label: 'Balance', data: [18200, 19400, 18850, 22100, 23600, 24800], borderColor: '#d7b56d', backgroundColor: 'rgba(215,181,109,.15)', tension: .42, fill: true }] },
            options: { responsive: true, plugins: { legend: { display: type === 'doughnut' } }, scales: type === 'doughnut' ? {} : { y: { beginAtZero: false } } }
        });
    });
});
