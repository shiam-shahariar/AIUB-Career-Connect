const reducedMotionPreference = window.matchMedia('(prefers-reduced-motion: reduce)');
const renderSlotCounter = (element, value) => {
    const text = String(value).trim();
    if (!/^\d[\d,]*$/.test(text)) return;
    if (reducedMotionPreference.matches) {
        element.textContent = text;
        return;
    }

    const digits = Array.from(text).filter((character) => /\d/.test(character));
    const accessibleValue = document.createElement('span');
    accessibleValue.className = 'sr-only';
    accessibleValue.textContent = text;

    const visualValue = document.createElement('span');
    visualValue.className = 'slot-counter__visual';
    visualValue.setAttribute('aria-hidden', 'true');

    let digitIndex = 0;
    Array.from(text).forEach((character) => {
        if (!/\d/.test(character)) {
            const separator = document.createElement('span');
            separator.className = 'slot-counter__separator';
            separator.dataset.separator = character;
            visualValue.append(separator);
            return;
        }

        const place = digits.length - digitIndex;
        const cycles = Math.min(5, Math.max(2, place));
        const column = document.createElement('span');
        column.className = 'slot-counter__column';
        const reel = document.createElement('span');
        reel.className = 'slot-counter__reel';
        reel.style.transitionDelay = `${(digits.length - digitIndex - 1) * 75}ms`;
        reel.style.transform = 'translate3d(0, 0, 0)';
        for (let cycle = 0; cycle <= cycles; cycle += 1) {
            for (let digit = 0; digit <= 9; digit += 1) {
                const item = document.createElement('span');
                item.className = 'slot-counter__digit';
                item.textContent = String(digit);
                reel.append(item);
            }
        }
        const destination = cycles * 10 + Number(character);
        column.dataset.destination = String(destination);
        column.append(reel);
        visualValue.append(column);
        digitIndex += 1;
    });

    element.replaceChildren(accessibleValue, visualValue);
    element.setAttribute('aria-label', text);
    window.requestAnimationFrame(() => {
        window.requestAnimationFrame(() => {
            visualValue.querySelectorAll('.slot-counter__column').forEach((column) => {
                const destination = Number(column.dataset.destination);
                const digitHeight = column.getBoundingClientRect().height;
                const reel = column.firstElementChild;
                if (reel instanceof HTMLElement) {
                    reel.style.transform = `translate3d(0, -${destination * digitHeight}px, 0)`;
                }
            });
        });
    });
};

const slotCounterObserver = 'IntersectionObserver' in window
    ? new IntersectionObserver((entries, observer) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            renderSlotCounter(entry.target, entry.target.textContent);
            observer.unobserve(entry.target);
        });
    }, { threshold: 0.2 })
    : null;

document.querySelectorAll('.stat-card [data-slot-counter], .hero-stats [data-slot-counter]').forEach((counter) => {
    if (slotCounterObserver) {
        slotCounterObserver.observe(counter);
    } else {
        renderSlotCounter(counter, counter.textContent);
    }
});

const navToggle = document.querySelector('.nav-toggle');
const primaryNavigation = document.querySelector('[data-primary-navigation]');
const mainNavs = Array.from(document.querySelectorAll('.main-nav'));
const closeNavMenus = (except = null) => {
    document.querySelectorAll('.nav-menu.is-open').forEach((menu) => {
        if (menu === except) return;
        menu.classList.remove('is-open');
        const trigger = menu.querySelector('.nav-menu__trigger');
        const dropdown = menu.querySelector('.nav-dropdown');
        trigger?.setAttribute('aria-expanded', 'false');
        if (dropdown instanceof HTMLElement) dropdown.hidden = true;
    });
};

if (navToggle && primaryNavigation) {
    navToggle.addEventListener('click', () => {
        const isOpen = primaryNavigation.classList.toggle('is-open');
        navToggle.setAttribute('aria-expanded', String(isOpen));
    });

    mainNavs.forEach((mainNav) => {
        mainNav.querySelectorAll('a').forEach((link) => {
            link.addEventListener('click', () => {
                primaryNavigation.classList.remove('is-open');
                navToggle.setAttribute('aria-expanded', 'false');
                closeNavMenus();
            });
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        const openMenu = document.querySelector('.nav-menu.is-open');
        if (openMenu) {
            closeNavMenus();
            openMenu.querySelector('.nav-menu__trigger')?.focus();
            return;
        }
        if (primaryNavigation.classList.contains('is-open')) {
            primaryNavigation.classList.remove('is-open');
            navToggle.setAttribute('aria-expanded', 'false');
            navToggle.focus();
        }
    });
}

document.querySelectorAll('.nav-menu').forEach((menu) => {
    const trigger = menu.querySelector('.nav-menu__trigger');
    const dropdown = menu.querySelector('.nav-dropdown');
    if (!(trigger instanceof HTMLButtonElement) || !(dropdown instanceof HTMLElement)) return;

    const setOpen = (isOpen) => {
        closeNavMenus(isOpen ? menu : null);
        menu.classList.toggle('is-open', isOpen);
        trigger.setAttribute('aria-expanded', String(isOpen));
        dropdown.hidden = !isOpen;
    };

    trigger.addEventListener('click', () => {
        setOpen(trigger.getAttribute('aria-expanded') !== 'true');
    });

    menu.addEventListener('pointerenter', (event) => {
        if (event.pointerType === 'mouse' && window.matchMedia('(min-width: 1081px)').matches) {
            setOpen(true);
        }
    });

    menu.addEventListener('pointerleave', (event) => {
        if (event.pointerType === 'mouse' && window.matchMedia('(min-width: 1081px)').matches
            && !menu.contains(document.activeElement)) {
            setOpen(false);
        }
    });

    menu.addEventListener('focusout', (event) => {
        if (event.relatedTarget instanceof Node && !menu.contains(event.relatedTarget)) {
            setOpen(false);
        }
    });
});

document.querySelectorAll('[data-managed-user-form]').forEach((form) => {
    if (!(form instanceof HTMLFormElement)) return;
    form.addEventListener('submit', (event) => {
        const status = form.querySelector('select[name="status"]');
        if (status?.value !== 'remove') return;
        const name = form.dataset.userName || 'this user';
        if (!window.confirm(`Permanently remove ${name} and all associated account data? This cannot be undone.`)) {
            event.preventDefault();
        }
    });
});

document.addEventListener('click', (event) => {
    if (event.target instanceof Element && !event.target.closest('.nav-menu')) {
        closeNavMenus();
    }
    if (primaryNavigation?.classList.contains('is-open')
        && event.target instanceof Node
        && !primaryNavigation.contains(event.target)
        && !navToggle?.contains(event.target)) {
        primaryNavigation.classList.remove('is-open');
        navToggle?.setAttribute('aria-expanded', 'false');
    }
});

const homeHeroSlides = Array.from(document.querySelectorAll('.hero__slide'));
if (homeHeroSlides.length > 1 && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    let activeHomeHeroSlide = homeHeroSlides.findIndex((slide) => slide.classList.contains('is-active'));
    if (activeHomeHeroSlide < 0) activeHomeHeroSlide = 0;
    const prepareNextHomeHeroSlide = () => {
        const nextSlide = homeHeroSlides[(activeHomeHeroSlide + 1) % homeHeroSlides.length];
        if (nextSlide instanceof HTMLImageElement && !nextSlide.hasAttribute('src') && nextSlide.dataset.src) {
            nextSlide.src = nextSlide.dataset.src;
        }
    };
    const advanceHomeHeroSlide = () => {
        const nextIndex = (activeHomeHeroSlide + 1) % homeHeroSlides.length;
        const nextSlide = homeHeroSlides[nextIndex];
        const activateNextSlide = () => {
            homeHeroSlides[activeHomeHeroSlide].classList.remove('is-active');
            activeHomeHeroSlide = nextIndex;
            nextSlide.classList.add('is-active');
            prepareNextHomeHeroSlide();
        };
        prepareNextHomeHeroSlide();
        if (nextSlide.complete && nextSlide.naturalWidth > 0) {
            activateNextSlide();
        } else {
            nextSlide.addEventListener('load', activateNextSlide, { once: true });
        }
    };
    window.setTimeout(prepareNextHomeHeroSlide, 5000);
    window.setInterval(() => {
        advanceHomeHeroSlide();
    }, 7000);
}

document.querySelectorAll('[data-admin-time-tools]').forEach((tools) => {
    if (!(tools instanceof HTMLElement)) return;
    const clock = tools.querySelector('[data-admin-clock]');
    const monthLabel = tools.querySelector('[data-calendar-month]');
    const daysContainer = tools.querySelector('[data-calendar-days]');
    const selectedLabel = tools.querySelector('[data-calendar-selected]');
    const timeZone = tools.dataset.timeZone || 'Asia/Dhaka';
    if (!(clock instanceof HTMLTimeElement)
        || !(monthLabel instanceof HTMLElement)
        || !(daysContainer instanceof HTMLTableSectionElement)
        || !(selectedLabel instanceof HTMLElement)) return;

    const getZonedDateParts = (date) => {
        const parts = new Intl.DateTimeFormat('en', {
            timeZone,
            year: 'numeric',
            month: 'numeric',
            day: 'numeric',
        }).formatToParts(date);
        return Object.fromEntries(parts
            .filter((part) => part.type !== 'literal')
            .map((part) => [part.type, Number(part.value)]));
    };
    const dateKey = (date) => date.toISOString().slice(0, 10);
    const formatLongDate = (date) => new Intl.DateTimeFormat('en', {
        timeZone: 'UTC',
        weekday: 'long',
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    }).format(date);
    const todayParts = getZonedDateParts(new Date());
    const today = new Date(Date.UTC(todayParts.year, todayParts.month - 1, todayParts.day));
    let displayedMonth = new Date(Date.UTC(todayParts.year, todayParts.month - 1, 1));
    let selectedDate = new Date(today);

    const updateClock = () => {
        const now = new Date();
        clock.textContent = new Intl.DateTimeFormat('en-GB', {
            timeZone,
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hourCycle: 'h23',
        }).format(now);
    };

    const renderCalendar = () => {
        monthLabel.textContent = new Intl.DateTimeFormat('en', {
            timeZone: 'UTC',
            month: 'long',
            year: 'numeric',
        }).format(displayedMonth);
        daysContainer.replaceChildren();
        const year = displayedMonth.getUTCFullYear();
        const month = displayedMonth.getUTCMonth();
        const firstWeekday = (new Date(Date.UTC(year, month, 1)).getUTCDay() + 6) % 7;
        const daysInMonth = new Date(Date.UTC(year, month + 1, 0)).getUTCDate();
        const weeks = Math.ceil((firstWeekday + daysInMonth) / 7);

        for (let week = 0; week < weeks; week += 1) {
            const row = document.createElement('tr');
            for (let weekday = 0; weekday < 7; weekday += 1) {
                const dayNumber = week * 7 + weekday - firstWeekday + 1;
                const cell = document.createElement('td');
                if (dayNumber > 0 && dayNumber <= daysInMonth) {
                    const date = new Date(Date.UTC(year, month, dayNumber));
                    const key = dateKey(date);
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'admin-calendar__day';
                    button.textContent = String(dayNumber);
                    button.setAttribute('aria-label', formatLongDate(date));
                    if (key === dateKey(today)) {
                        button.classList.add('is-today');
                        button.setAttribute('aria-current', 'date');
                    }
                    if (key === dateKey(selectedDate)) {
                        button.classList.add('is-selected');
                        button.setAttribute('aria-pressed', 'true');
                    } else {
                        button.setAttribute('aria-pressed', 'false');
                    }
                    button.addEventListener('click', () => {
                        selectedDate = date;
                        selectedLabel.textContent = `Selected: ${formatLongDate(selectedDate)}`;
                        renderCalendar();
                    });
                    cell.append(button);
                }
                row.append(cell);
            }
            daysContainer.append(row);
        }
        selectedLabel.textContent = `Selected: ${formatLongDate(selectedDate)}`;
    };

    tools.querySelector('[data-calendar-prev]')?.addEventListener('click', () => {
        displayedMonth = new Date(Date.UTC(displayedMonth.getUTCFullYear(), displayedMonth.getUTCMonth() - 1, 1));
        renderCalendar();
    });
    tools.querySelector('[data-calendar-next]')?.addEventListener('click', () => {
        displayedMonth = new Date(Date.UTC(displayedMonth.getUTCFullYear(), displayedMonth.getUTCMonth() + 1, 1));
        renderCalendar();
    });
    tools.querySelector('[data-calendar-today]')?.addEventListener('click', () => {
        selectedDate = new Date(today);
        displayedMonth = new Date(Date.UTC(today.getUTCFullYear(), today.getUTCMonth(), 1));
        renderCalendar();
    });

    updateClock();
    window.setInterval(updateClock, 1000);
    renderCalendar();
});

const filterForm = document.getElementById('opportunity-filter-form');
if (filterForm) {
    const escapeHtml = (value) => String(value).replace(/[&<>"']/g, (character) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;',
    })[character]);

    const resultsContainer = document.getElementById('opportunity-results');
    const pagination = document.getElementById('opportunity-pagination');
    const filterChips = document.getElementById('active-opportunity-filters');
    const resultsCount = document.querySelector('.results-count');
    let activeSearchController = null;
    const searchOpportunities = async (page = 1) => {
        const params = new URLSearchParams(new FormData(filterForm));
        params.set('ajax', '1');
        params.set('page', 'opportunities');
        params.set('page_number', String(page));

        if (!resultsContainer) return;

        activeSearchController?.abort();
        const controller = new AbortController();
        activeSearchController = controller;
        resultsContainer.setAttribute('aria-busy', 'true');
        const submitButton = filterForm.querySelector('button[type="submit"]');
        if (submitButton instanceof HTMLButtonElement) submitButton.disabled = true;

        try {
            const response = await fetch(`index.php?${params.toString()}`, { signal: controller.signal });
            const data = await response.json();

            if (!response.ok || !data.success || !Array.isArray(data.results)) {
                throw new Error(data.message || 'Unable to load opportunities. Please try again later.');
            }

            const currentPage = Number(data.page);
            const totalPages = Number(data.pages);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            if (data.results.length === 0) {
                resultsContainer.innerHTML = '<div class="message-panel opportunity-empty-state"><h2>No matching opportunities</h2><p>Try broader filters or clear them to browse all current listings.</p></div>';
            } else {
                resultsContainer.innerHTML = data.results.map((opportunity) => {
                    const deadline = new Date(`${opportunity.deadline}T00:00:00`);
                    const daysLeft = Math.max(0, Math.round((deadline - today) / 86400000));
                    return `
                <article class="listing-card opportunity-card">
                    <div class="listing-card__meta">
                        <span class="badge badge--soft">${escapeHtml(opportunity.type)}</span>
                        <span>${escapeHtml(opportunity.location)} · ${escapeHtml(opportunity.remote || '')}</span>
                    </div>
                    <h2>${escapeHtml(opportunity.title)}</h2>
                    <p class="company-name">${escapeHtml(opportunity.company)}</p>
                    <p class="opportunity-card__description">${escapeHtml(opportunity.description)}</p>
                    <div class="listing-card__foot">
                        <span class="deadline-badge${daysLeft <= 3 ? ' deadline-badge--soon' : ''}">
                            ${daysLeft === 0 ? 'Last day to apply' : `${daysLeft} ${daysLeft === 1 ? 'day' : 'days'} left`}
                            <small>Deadline: ${escapeHtml(deadline.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' }))}</small>
                        </span>
                        <div class="card-actions">
                            <a href="index.php?page=opportunity-detail&id=${Number(opportunity.id)}" class="btn btn--secondary btn--outlined">View details</a>
                            ${document.body.dataset.userRole === 'student' ? `<form method="post" action="index.php"><input type="hidden" name="action" value="save_application"><input type="hidden" name="csrf_token" value="${escapeHtml(document.querySelector('input[name="csrf_token"]')?.value || '')}"><input type="hidden" name="opportunity_id" value="${Number(opportunity.id)}"><input type="hidden" name="status" value="saved"><input type="hidden" name="application_date" value=""><input type="hidden" name="private_note" value=""><button type="submit" class="btn btn--primary">Save to tracker</button></form>` : ''}
                        </div>
                        ${opportunity.application_available ? '' : '<p class="contact-note" role="status">Employer application destination unavailable; this listing has no valid external application link.</p>'}
                    </div>
                </article>
                `;
                }).join('');
            }
            if (resultsCount) {
                resultsCount.textContent = `${Number(data.count)} current ${Number(data.count) === 1 ? 'opportunity' : 'opportunities'}`;
            }
            if (filterChips) {
                const labels = new Map([
                    ['keyword', 'Keyword'],
                    ['type', 'Type'],
                    ['department', 'Department'],
                    ['location', 'Location'],
                    ['remote', 'Work mode'],
                    ['sort', 'Sort'],
                ]);
                const sortLabels = new Map([['deadline', 'Deadline soonest'], ['title', 'Title A–Z']]);
                const selected = Array.from(new FormData(filterForm).entries())
                    .filter(([name, value]) => name !== 'page' && value !== '')
                    .map(([name, value]) => `<button type="button" class="filter-chip" data-remove-filter="${escapeHtml(name)}">${escapeHtml(labels.get(name) || name)}: ${escapeHtml(name === 'sort' ? (sortLabels.get(value) || value) : value)} <span aria-hidden="true">×</span></button>`);
                filterChips.innerHTML = selected.join('');
                filterChips.hidden = selected.length === 0;
            }
            if (pagination) {
                pagination.hidden = totalPages <= 1;
                pagination.innerHTML = `${currentPage > 1 ? `<button type="button" class="btn btn--secondary" data-opportunity-page="${currentPage - 1}">Previous</button>` : ''}<span>Page ${currentPage} of ${totalPages}</span>${currentPage < totalPages ? `<button type="button" class="btn btn--secondary" data-opportunity-page="${currentPage + 1}">Next</button>` : ''}`;
            }
            const pageUrl = new URL(window.location.href);
            pageUrl.search = new URLSearchParams(new FormData(filterForm)).toString();
            if (currentPage > 1) pageUrl.searchParams.set('page_number', String(currentPage));
            window.history.replaceState({}, '', pageUrl);
        } catch (error) {
            if (controller.signal.aborted) return;
            const message = error instanceof Error
                ? error.message
                : 'Unable to load opportunities. Please try again later.';
            resultsContainer.innerHTML = `<div class="message-panel"><h2>Unable to load opportunities.</h2><p>${escapeHtml(message)}</p></div>`;
        } finally {
            if (activeSearchController === controller) {
                activeSearchController = null;
                resultsContainer.setAttribute('aria-busy', 'false');
                if (submitButton instanceof HTMLButtonElement) submitButton.disabled = false;
            }
        }
    };

    filterForm.addEventListener('submit', (event) => {
        event.preventDefault();
        searchOpportunities();
    });
    filterForm.addEventListener('reset', () => {
        window.setTimeout(() => searchOpportunities(), 0);
    });
    pagination?.addEventListener('click', (event) => {
        if (!(event.target instanceof Element)) return;
        const button = event.target.closest('[data-opportunity-page]');
        if (button) searchOpportunities(Number(button.getAttribute('data-opportunity-page')));
    });
    filterChips?.addEventListener('click', (event) => {
        if (!(event.target instanceof Element)) return;
        const button = event.target.closest('[data-remove-filter]');
        const field = button?.getAttribute('data-remove-filter');
        const input = field ? filterForm.elements.namedItem(field) : null;
        if (input instanceof HTMLInputElement || input instanceof HTMLSelectElement) {
            input.value = '';
            searchOpportunities();
        }
    });
}

document.querySelectorAll('form').forEach((form) => {
    form.querySelectorAll('[maxlength]').forEach((field) => {
        if (!(field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement)) return;
        const counter = document.createElement('small');
        counter.className = 'character-counter';
        counter.setAttribute('aria-live', 'polite');
        const updateCounter = () => {
            counter.textContent = `${field.value.length} / ${field.maxLength}`;
        };
        field.insertAdjacentElement('afterend', counter);
        field.addEventListener('input', updateCounter);
        updateCounter();
    });
    form.addEventListener('invalid', (event) => {
        const field = event.target;
        if (!(field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement || field instanceof HTMLSelectElement)
            || field.type === 'hidden') return;
        field.setAttribute('aria-invalid', 'true');
        const label = field.closest('label');
        if (!label) return;
        let feedback = label.querySelector('.field-validation-message');
        if (!feedback) {
            feedback = document.createElement('small');
            feedback.className = 'field-validation-message';
            feedback.setAttribute('aria-live', 'polite');
            label.append(feedback);
        }
        feedback.textContent = field.validationMessage;
    }, true);
    form.addEventListener('input', (event) => {
        const field = event.target;
        if (!(field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement || field instanceof HTMLSelectElement)) return;
        if (field.validity.valid) {
            field.removeAttribute('aria-invalid');
            field.closest('label')?.querySelector('.field-validation-message')?.remove();
        }
    });

    const password = form.querySelector('input[name="password"]');
    const confirmation = form.querySelector('input[name="confirm_password"]');
    if (password instanceof HTMLInputElement) {
        const eyeIcon = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
        const eyeOffIcon = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 3 21 21"></path><path d="M10.6 5.2A10.7 10.7 0 0 1 12 5c6.4 0 10 7 10 7a16 16 0 0 1-3.1 4.1M6.2 6.2C3.5 8 2 12 2 12s3.6 7 10 7c1.4 0 2.7-.3 3.8-.8"></path><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"></path></svg>';
        const addPasswordVisibilityToggle = (field, label) => {
            const toggle = document.createElement('button');
            toggle.type = 'button';
            toggle.className = 'password-toggle';
            toggle.innerHTML = eyeIcon;
            toggle.setAttribute('aria-label', `Show ${label}`);
            toggle.setAttribute('title', `Show ${label}`);
            const group = document.createElement('div');
            group.className = 'password-input-group';
            field.replaceWith(group);
            group.append(field, toggle);
            toggle.addEventListener('click', () => {
                const visible = field.type === 'password';
                field.type = visible ? 'text' : 'password';
                toggle.innerHTML = visible ? eyeOffIcon : eyeIcon;
                toggle.setAttribute('aria-label', `${visible ? 'Hide' : 'Show'} ${label}`);
                toggle.setAttribute('title', `${visible ? 'Hide' : 'Show'} ${label}`);
            });
        };
        addPasswordVisibilityToggle(password, 'password');

        if (confirmation instanceof HTMLInputElement) {
            addPasswordVisibilityToggle(confirmation, 'confirm password');
            const feedback = document.createElement('small');
            feedback.className = 'field-feedback';
            feedback.setAttribute('aria-live', 'polite');
            const confirmationCounter = confirmation.closest('label')?.querySelector('.character-counter');
            (confirmationCounter ?? confirmation).insertAdjacentElement('afterend', feedback);
            const validateMatch = () => {
                const matches = confirmation.value === '' || confirmation.value === password.value;
                confirmation.setCustomValidity(matches ? '' : 'Passwords do not match.');
                if (matches) confirmation.removeAttribute('aria-invalid');
                else confirmation.setAttribute('aria-invalid', 'true');
                feedback.textContent = matches || confirmation.value === '' ? '' : 'Passwords do not match.';
            };
            password.addEventListener('input', validateMatch);
            confirmation.addEventListener('input', validateMatch);
        }
    }
});

document.querySelectorAll('[data-tag-entry]').forEach((entry) => {
    if (!(entry instanceof HTMLInputElement)) return;
    const wrapper = entry.closest('[data-tag-input]');
    let valueField = wrapper?.querySelector('[data-tag-value]');
    const tagList = wrapper?.querySelector('[data-tag-list]');
    if (!(wrapper instanceof HTMLElement) || !(tagList instanceof HTMLElement)) return;
    if (!(valueField instanceof HTMLInputElement)) {
        valueField = document.createElement('input');
        valueField.type = 'hidden';
        valueField.name = entry.name;
        valueField.dataset.tagValue = '';
        valueField.value = entry.value;
        wrapper.append(valueField);
    }
    if (!entry.name) return;
    let tags = valueField.value.split(/[,;\n]+/).map((tag) => tag.trim()).filter(Boolean);
    entry.removeAttribute('name');
    entry.removeAttribute('maxlength');
    entry.value = '';

    const render = () => {
        tagList.innerHTML = '';
        tags.forEach((tag, index) => {
            const item = document.createElement('li');
            item.className = 'skill-tag';
            const label = document.createElement('span');
            label.textContent = tag;
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'skill-tag__remove';
            remove.textContent = '×';
            remove.setAttribute('aria-label', `Remove ${tag}`);
            remove.addEventListener('click', () => {
                tags = tags.filter((_, tagIndex) => tagIndex !== index);
                render();
            });
            item.append(label, remove);
            tagList.append(item);
        });
        valueField.value = tags.join(', ');
        const counter = wrapper.querySelector('.character-counter');
        if (counter) counter.textContent = `${valueField.value.length} / 2000`;
    };
    const addTag = () => {
        const tag = entry.value.trim().replace(/[,;]+$/, '');
        if (tag && !tags.some((existing) => existing.toLocaleLowerCase() === tag.toLocaleLowerCase())) {
            const currentLength = tags.join(', ').length;
            if (currentLength + tag.length + 2 <= 2000) tags.push(tag);
        }
        entry.value = '';
        render();
    };
    entry.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ',') {
            event.preventDefault();
            addTag();
        } else if (event.key === 'Backspace' && entry.value === '' && tags.length) {
            tags.pop();
            render();
        }
    });
    entry.addEventListener('blur', () => {
        if (entry.value.trim()) addTag();
    });
    entry.form?.addEventListener('submit', () => {
        if (entry.value.trim()) addTag();
    });
    render();
});

document.querySelectorAll('[data-admin-tabs]').forEach((tabNav) => {
    if (!(tabNav instanceof HTMLElement)) return;
    const links = Array.from(tabNav.querySelectorAll('a[href^="#"]'));
    const panels = links
        .map((link) => document.querySelector(link.getAttribute('href')))
        .filter((panel) => panel instanceof HTMLElement);
    if (!links.length || links.length !== panels.length) return;
    const activate = (index, updateHash = true) => {
        links.forEach((link, linkIndex) => {
            link.setAttribute('role', 'tab');
            link.setAttribute('aria-selected', String(index === linkIndex));
            link.setAttribute('tabindex', index === linkIndex ? '0' : '-1');
            const panel = panels[linkIndex];
            link.setAttribute('aria-controls', panel.id);
            panel.setAttribute('role', 'tabpanel');
            panel.setAttribute('aria-labelledby', link.id);
            panel.setAttribute('tabindex', '0');
            panel.hidden = index !== linkIndex;
        });
        if (updateHash) history.replaceState({}, '', `${location.pathname}${location.search}#${panels[index].id}`);
    };
    tabNav.setAttribute('role', 'tablist');
    tabNav.setAttribute('aria-label', 'Administration sections');
    links.forEach((link, index) => {
        link.id ||= `admin-tab-${index + 1}`;
        link.addEventListener('click', (event) => {
            event.preventDefault();
            activate(index);
        });
        link.addEventListener('keydown', (event) => {
            if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
            event.preventDefault();
            const nextIndex = event.key === 'Home' ? 0
                : event.key === 'End' ? links.length - 1
                    : (index + (event.key === 'ArrowRight' ? 1 : -1) + links.length) % links.length;
            activate(nextIndex);
            links[nextIndex].focus();
        });
    });
    let initialIndex = links.findIndex((link) => link.hash === location.hash);
    if (initialIndex < 0 && location.hash) {
        const target = document.getElementById(location.hash.slice(1));
        initialIndex = panels.findIndex((panel) => target && panel.contains(target));
    }
    initialIndex = Math.max(0, initialIndex);
    activate(initialIndex, false);
});

document.querySelectorAll('[data-application-filter]').forEach((filter) => {
    filter.addEventListener('click', () => {
        if (!(filter instanceof HTMLButtonElement)) return;
        const status = filter.dataset.applicationFilter;
        document.querySelectorAll('[data-application-filter]').forEach((button) => {
            button.classList.toggle('is-active', button === filter);
            button.setAttribute('aria-pressed', String(button === filter));
        });
        document.querySelectorAll('[data-application-status]').forEach((card) => {
            if (card instanceof HTMLElement) {
                card.hidden = status !== 'all' && card.dataset.applicationStatus !== status;
            }
        });
    });
    if (filter instanceof HTMLButtonElement) {
        filter.setAttribute('aria-pressed', String(filter.classList.contains('is-active')));
    }
});
