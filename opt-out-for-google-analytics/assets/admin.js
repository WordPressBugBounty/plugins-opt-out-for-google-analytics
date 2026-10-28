jQuery(function ($) {

    // The settings page is a fixed stage, which would cover the help of WordPress. So the help is moved into the stage.
    $('#screen-meta, #screen-meta-links').prependTo('.gaoo-settings-scroll');

    // If the admin menu is higher than the window, WordPress scrolls the whole page. Then the stage scrolls with the page,
    // otherwise there would be two scrollbars (page and stage).
    function setPageScroll() {
        var menuHeight = $('#adminmenuwrap').outerHeight() || 0,
            adminBarHeight = $('#wpadminbar').outerHeight() || 0;

        var pageScroll = menuHeight + adminBarHeight > window.innerHeight;

        if (pageScroll !== $('body').hasClass('gaoo-page-scroll')) {
            $('body').toggleClass('gaoo-page-scroll', pageScroll);
            // The height of the page changed, so WordPress has to recalculate the position of the menu (it moves with the page).
            $(document).trigger('wp-pin-menu');
        }
    }

    setPageScroll();
    $(window).on('resize', setPageScroll);
    $(document).on('wp-collapse-menu', function () {
        setTimeout(setPageScroll, 0);
    });

    // Unsaved changes: ask before leaving the page with a link. OK saves the changes (and opens the link afterwards).
    // Cancel asks again, whether to leave the page without saving or to stay. Other ways to leave the page (e.g. closing the tab)
    // get the dialog of the browser, which can't save.
    var $form = $('#gaoo-settings-form'),
        savedState = $form.serialize(),
        leaving = false,
        afterSaveUrl = '';

    function isDirty() {
        return $form.length && !leaving && $form.serialize() !== savedState;
    }

    // The page is left only for a moment: a link can also be a download (e.g. the export), then the page stays and is protected again.
    function setLeaving() {
        leaving = true;
        setTimeout(function () {
            leaving = false;
        }, 3000);
    }

    $form.on('submit', function (event) {
        if (event.isDefaultPrevented()) {
            return;
        }

        leaving = true;

        // Open the link after saving. Only stored, if the form is really sent (not stopped by the check of the browser).
        if (afterSaveUrl) {
            try {
                sessionStorage.setItem('gaoo-after-save', afterSaveUrl);
            } catch (e) {
            }
        }
    });

    // The browser shows its own dialog. Older browsers need the returnValue.
    function warnBeforeUnload(event) {
        event.preventDefault();
        event.originalEvent.returnValue = '';
        return '';
    }

    $(document).on('click', 'a[href]', function (event) {
        var href = this.getAttribute('href') || '',
            url;

        // Downloads (e.g. the export) do not leave the page: no question, also not the one of the browser. The page is protected again afterwards.
        if (isDirty() && (this.hasAttribute('download') || $(this).hasClass('gaoo-no-leave'))) {
            setLeaving();
            return;
        }

        if (!isDirty() || event.isDefaultPrevented() || this.target === '_blank' || href.charAt(0) === '#' || href.indexOf('javascript:') === 0 || event.ctrlKey || event.metaKey || event.shiftKey) {
            return;
        }

        url = this.href;
        event.preventDefault();

        if (window.confirm(gaoo.text.unsaved)) {
            // The submit event is sent at once, so the link is only kept for this submit.
            afterSaveUrl = url;
            $form.find('#submit').trigger('click');
            afterSaveUrl = '';
        } else if (window.confirm(gaoo.text.leave)) {
            setLeaving();
            window.location.href = url;
        }
    });

    // The import replaces the current settings, so it must be confirmed. Unsaved changes are lost anyway.
    $('#gaoo-import-form').on('submit', function (event) {
        if (!window.confirm(gaoo.text.import)) {
            event.preventDefault();
            return;
        }

        setLeaving();
    });

    $(window).on('beforeunload', function (event) {
        if (isDirty()) {
            return warnBeforeUnload(event);
        }
    });

    // After saving from the question above: open the link, if there are no errors or warnings.
    try {
        var afterSave = sessionStorage.getItem('gaoo-after-save');

        sessionStorage.removeItem('gaoo-after-save');

        if (afterSave && $('.gaoo-messages.notice-success').length && !$('.gaoo-messages.notice-error, .gaoo-messages.notice-warning').length) {
            leaving = true;
            window.location.href = afterSave;
        }
    } catch (e) {
    }

    // Preview of the link in a sandbox (iframe without access to this page), so the custom CSS can't change the admin.
    var $preview = $('#gaoo-preview'),
        previewTimer = null;

    function escapeHtml(text) {
        return String(text).replace(/[&<>"']/g, function (char) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[char];
        });
    }

    // Runs in the preview: toggles the link and shows the popup text, like assets/public.js, without cookies.
    function previewScript(config) {
        var link = document.querySelector('.gaoo-link'),
            disabled = false;

        function resize() {
            // The height of the content, not of the document: it is at least as high as the iframe, so it would never shrink.
            parent.postMessage({gaooPreviewHeight: Math.ceil(document.body.getBoundingClientRect().height)}, '*');
        }

        link.addEventListener('click', function (event) {
            var text, notice;

            event.preventDefault();
            disabled = !disabled;
            link.textContent = disabled ? config.linkActivate : config.linkDeactivate;
            link.className = 'gaoo-link gaoo-link-' + (disabled ? 'activate' : 'deactivate');
            text = disabled ? config.popupDeactivate : config.popupActivate;

            if (text && config.notice) {
                notice = link.nextElementSibling;

                if (!notice || notice.className !== 'gaoo-notice') {
                    notice = document.createElement('span');
                    notice.className = 'gaoo-notice';
                    link.parentNode.insertBefore(notice, link.nextSibling);
                }

                notice.textContent = text;
            } else if (text) {
                alert(text);
            }

            resize();
        });

        window.addEventListener('load', resize);
        resize();
    }

    function updatePreview() {
        var config = {
                linkDeactivate: $('#gaoo-link-deactivate').val(),
                linkActivate: $('#gaoo-link-activate').val(),
                popupDeactivate: $('#gaoo-popup-deactivate').val(),
                popupActivate: $('#gaoo-popup-activate').val(),
                notice: $('#gaoo-popup-notice').is(':checked'),
            },
            css = $('#gaoo-custom-css-status').is(':checked') ? String($('#gaoo-custom-css').val() || '') : '';

        // The custom CSS can't close the style element.
        css = css.replace(/<\/style/gi, '<\\/style');

        $preview.attr('srcdoc', '<!doctype html><html><head><meta charset="utf-8">'
            + '<style>body{margin:0;padding:16px 20px;font:15px/1.6 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif;color:#1e1e1e}a{color:#2271b1}p{margin:0}.gaoo-notice{display:block;white-space:pre-line;margin-top:.5em}</style>'
            + '<style>' + css + '</style></head><body><p>' + escapeHtml(gaoo.text.preview) + ' <a href="#" role="button" class="gaoo-link gaoo-link-deactivate">' + escapeHtml(config.linkDeactivate) + '</a></p>'
            + '<script>(' + previewScript.toString() + ')(' + JSON.stringify(config).replace(/</g, '\\u003c') + ');<\/script></body></html>');
    }

    if ($preview.length) {
        window.addEventListener('message', function (event) {
            if ($preview[0] && event.source === $preview[0].contentWindow && event.data && event.data.gaooPreviewHeight) {
                $preview.css('height', Math.min(400, Math.max(60, event.data.gaooPreviewHeight + 2)) + 'px');
            }
        });

        $('#gaoo-link-deactivate, #gaoo-link-activate, #gaoo-popup-deactivate, #gaoo-popup-activate, #gaoo-popup-notice, #gaoo-custom-css-status, #gaoo-custom-css').on('input change', function () {
            clearTimeout(previewTimer);
            previewTimer = setTimeout(updatePreview, 400);
        });

        // The CSS classes are added to the CSS by a click, which does not fire an input event of the field.
        $('.gaoo-css-class, .gaoo-empty-popup').on('click', function () {
            clearTimeout(previewTimer);
            previewTimer = setTimeout(updatePreview, 400);
        });

        updatePreview();
    }

    // Sections which are only shown, while their toggle is enabled (webhook, custom CSS). Also elements in the label, e.g. the link to the help.
    $('.gaoo-section-toggle').on('change', function () {
        var $fields = $('#' + this.getAttribute('aria-controls'));

        $fields.prop('hidden', !this.checked);
        $(this).closest('.gaoo-toggle').find('.gaoo-if-on').prop('hidden', !this.checked);

        // The line numbers and the preview need the size of the visible field.
        if (this.checked) {
            $fields.find('textarea').trigger('input');
        }
    });

    // Network admin: warning before leaving the page with changes in a form. The search does not change anything.
    var $networkForms = $('.gaoo-network form').not('.gaoo-network-search'),
        networkState = $networkForms.serialize(),
        networkLeaving = false;

    // The transfer overwrites the settings of all sites, so it must be confirmed. Bound first, so the handler below knows the answer.
    $('#gaoo-transfer-form').on('submit', function (event) {
        if (!window.confirm(gaoo.text.transfer)) {
            event.preventDefault();
        }
    });

    $networkForms.on('submit', function (event) {
        if (!event.isDefaultPrevented()) {
            networkLeaving = true;
        }
    });

    $(window).on('beforeunload', function (event) {
        if ($networkForms.length && !networkLeaving && $networkForms.serialize() !== networkState) {
            return warnBeforeUnload(event);
        }
    });

    // Navigation of the sections: highlight the section, which is at the top of the visible area.
    var nav = document.querySelector('.gaoo-nav'),
        sentinel = document.querySelector('.gaoo-nav-sentinel'),
        stage = document.querySelector('.gaoo-settings-scroll'),
        $navLinks = $('.gaoo-nav a'),
        $sections = $navLinks.map(function () {
            return document.getElementById(this.getAttribute('href').slice(1));
        }),
        currentSection = null,
        navFrame = null;

    function highlightSection() {
        navFrame = null;

        var pageScroll = $('body').hasClass('gaoo-page-scroll'),
            top = nav.getBoundingClientRect().bottom + 24,
            scroller = pageScroll ? document.documentElement : stage,
            current = $sections.get(0);

        $sections.each(function () {
            if (this.getBoundingClientRect().top <= top) {
                current = this;
            }
        });

        // At the end of the page the last sections can not reach the top, so the last one is highlighted.
        if (scroller && scroller.scrollTop > 0 && scroller.scrollTop + scroller.clientHeight >= scroller.scrollHeight - 2) {
            current = $sections.get($sections.length - 1);
        }

        // The navigation stays at the top: then it is separated from the content.
        nav.classList.toggle('is-stuck', sentinel.getBoundingClientRect().top < nav.getBoundingClientRect().top - 1);

        // The sections are not hidden below the navigation after a jump, also if it has two rows or the page scrolls below the admin bar.
        document.documentElement.style.setProperty('--gaoo-nav-offset', (nav.offsetHeight + 16 + (pageScroll ? ($('#wpadminbar').outerHeight() || 0) : 0)) + 'px');

        // The links are only changed, if the section changed.
        if (current !== currentSection) {
            currentSection = current;
            $navLinks.removeClass('is-current').removeAttr('aria-current');
            $navLinks.filter('[href="#' + current.id + '"]').addClass('is-current').attr('aria-current', 'true');
        }
    }

    // At most once per frame while scrolling.
    function requestHighlight() {
        if (!navFrame) {
            navFrame = window.requestAnimationFrame(highlightSection);
        }
    }

    if (nav && sentinel && $navLinks.length && $sections.length) {
        $(stage).on('scroll', requestHighlight);
        $(window).on('scroll resize', requestHighlight);
        highlightSection();
    }

    // Open the help on a specific tab, e.g. from the shortcode box.
    $('.gaoo-open-help').on('click', function (event) {
        // The link can be in a label, then the click must not change the toggle.
        event.preventDefault();

        if (!$('#contextual-help-wrap').is(':visible')) {
            $('#contextual-help-link').trigger('click');
        }

        $('#tab-link-' + $(this).data('tab') + ' a').trigger('click');
        $('.gaoo-settings-scroll').scrollTop(0);
    });

    var $wp_privacy_page = $('#gaoo-wp-privacy-page');
    var $privacy_page = $('#gaoo-privacy-page');
    var $wp_admin_mail = $('#gaoo-status-mails-sync');

    $wp_admin_mail.on('click', function () {
        var $this = $(this),
            $mail_input = $('#gaoo-status-mails'),
            admin_mail = $this.data('mail'),
            is_checked = $this.is(':checked');

        if (is_checked) {
            $this.data('old', $mail_input.val());
        } else {
            // Without a former value (the sync was already on when the page was loaded), the admin email stays in the field.
            admin_mail = typeof $this.data('old') === 'undefined' ? $mail_input.val() : $this.data('old');
        }

        $mail_input
            .val(admin_mail)
            .prop('readonly', is_checked)
            .trigger('input');
    });

    if (typeof $wp_privacy_page !== 'undefined' && $wp_privacy_page.length) {
        var old_page_id = 0;

        if ($wp_privacy_page.is(':checked')) {
            $privacy_page.prop('disabled', 'disabled');
        }

        $wp_privacy_page.on('click', function () {
            if ($(this).is(':checked')) {
                var page_id = $wp_privacy_page.data('id');

                $privacy_page.prop('disabled', 'disabled');

                if (page_id) {
                    old_page_id = $privacy_page.val();
                    // The change event updates the link to edit the page.
                    $privacy_page.val(page_id).trigger('change');
                }
            } else {
                $privacy_page.prop('disabled', false);

                if (old_page_id) {
                    $privacy_page.val(old_page_id).trigger('change');
                }
            }
        });
    }

    $privacy_page.on('change', function () {
        var val = $('option:selected', this).val(),
            $edit_link = $('#gaoo .gaoo-edit-link');

        if (val == 0 && !$edit_link.hasClass('hide'))
            $edit_link.addClass('hide');
        else
            $edit_link.removeClass('hide').attr('href', gaoo.edit_link.replace('%d', val));
    });

    $('input[name="gaoo[ga_plugin]"]').on('change', function () {
        var $this = $(this),
            $manual_details = $('.gaoo-source-details');

        if ($this.val() != 'manual') {
            $manual_details.slideUp();
        } else {
            $manual_details.removeClass('hide').hide().slideDown();
        }
    });

    $('#ga-plugin-tracking-code').on('focusout keyup paste', function () {
        var val = $(this).val(),
            $input_ua = $('#gaoo-ua-code');

        if (val.length && !$input_ua.val().trim().length) {
            var matches = val.toString().match(/\bG-[a-zA-Z0-9]+/gm);

            if (matches != null) {
                $input_ua.val(matches.shift());
            }
        }
    });

    $('.gaoo-empty-popup').on('click', function () {
        var $this = $(this);

        $this.prev('input[type="text"], input[type="email"], textarea').val('').trigger('focus');
        $this.addClass('empty');
    });

    // Show the button to empty the popup text again, if a text is entered.
    $('.gaoo-input-clear input[type="text"], .gaoo-input-clear input[type="email"], .gaoo-input-clear textarea').on('input', function () {
        $(this).next('.gaoo-empty-popup').toggleClass('empty', !$(this).val().length);
    });

    $('.gaoo-copy').on('click', function () {
        var $feedback = $('.gaoo-clipboard', this);

        // The result is shown, when the copy is done.
        copyTextToClipboard(String($(this).data('copy')), this).then(function () {
            $feedback.text(gaoo.text.copied);
        }, function () {
            $feedback.text(gaoo.text.notcopied);
        }).then(function () {
            setTimeout(function () {
                $feedback.text('');
            }, 1500);
        });
    });

    // The saved state after all changes of the page by the JavaScript (e.g. the disabled field of the privacy policy page),
    // otherwise the page would always have unsaved changes.
    setTimeout(function () {
        savedState = $form.serialize();
        networkState = $networkForms.serialize();
    }, 0);
});

/**
 * Copy a text to the clipboard. The fallback for old browsers or pages without HTTPS uses a hidden field.
 *
 * @param {string} text Text.
 * @param {Element} button The button, which gets the focus again.
 * @returns {Promise} Resolved, if the text was copied.
 */
function copyTextToClipboard(text, button) {
    if (navigator.clipboard && window.isSecureContext) {
        return navigator.clipboard.writeText(text);
    }

    return new Promise(function (resolve, reject) {
        var textArea = document.createElement('textarea'),
            copied = false;

        // Outside of the visible area and fixed, so the page does not scroll.
        textArea.value = text;
        textArea.setAttribute('readonly', '');
        textArea.style.cssText = 'position:fixed;top:0;left:-9999px;opacity:0';
        document.body.appendChild(textArea);
        textArea.select();

        try {
            copied = document.execCommand('copy');
        } catch (e) {
            copied = false;
        }

        document.body.removeChild(textArea);

        if (button) {
            button.focus();
        }

        if (copied) {
            resolve();
        } else {
            reject();
        }
    });
}

// Custom CSS: add a class by click, highlight the used classes and check the code while typing.
// The check is the same as in inc/css.class.php, which is done when saving.
jQuery(function ($) {
    var $css = $('#gaoo-custom-css'),
        $code = $css.closest('.gaoo-code'),
        $lines = $('.gaoo-code-lines'),
        $classes = $('.gaoo-css-class'),
        $check = $('.gaoo-css-check'),
        errorLines = [],
        timer = null;

    if (!$css.length || typeof gaoo === 'undefined' || !gaoo.css_errors) {
        return;
    }

    // Replace the content of comments, quotes and brackets with spaces, so their characters are ignored. Line breaks are kept.
    function mask(text) {
        var result = '',
            depth = 0,
            quote = null,
            comment = false,
            i, ch;

        for (i = 0; i < text.length; i++) {
            ch = text[i];

            if (comment) {
                if (ch === '*' && text[i + 1] === '/') {
                    comment = false;
                    result += '  ';
                    i++;
                    continue;
                }
            } else if (quote) {
                if (ch === '\\') {
                    result += text[i + 1] === '\n' ? ' \n' : '  ';
                    i++;
                    continue;
                }

                if (ch === quote) {
                    quote = null;
                }
            } else if (ch === '/' && text[i + 1] === '*') {
                comment = true;
                result += '  ';
                i++;
                continue;
            } else if (ch === '"' || ch === "'") {
                quote = ch;
            } else if (ch === '(') {
                depth++;
            } else if (ch === ')' && depth > 0) {
                depth--;
                result += ' ';
                continue;
            } else if (!depth) {
                result += ch;
                continue;
            }

            result += ch === '\n' ? '\n' : ' ';
        }

        return result;
    }

    function shorten(text) {
        text = text.replace(/\s+/g, ' ');

        return text.length > 40 ? text.substring(0, 40) + '…' : text;
    }

    function countLines(text) {
        return (text.match(/\n/g) || []).length;
    }

    // Check the CSS code, returns a list of errors with the keys "code", "line" and "text".
    function checkCss(css) {
        var errors = [],
            stack = [{line: 0}],
            buffer = '',
            bufferLine = 0,
            parens = [],
            line = 1,
            i = 0,
            end, ch, text;

        function error(code, errorLine, errorText) {
            errors.push({code: code, line: errorLine, text: errorText || ''});
        }

        function isBlank(value) {
            return !/\S/.test(value);
        }

        function checkDeclaration(declaration, declarationLine) {
            var masked, match, value, next;

            if (declaration[0] === '@') {
                return;
            }

            if (stack.length < 2) {
                error('brace_open', declarationLine);
                return;
            }

            match = declaration.match(/^(--[\w-]+|[*_]?-?[a-zA-Z][\w-]*)\s*:/);

            if (!match) {
                error('declaration', declarationLine, shorten(declaration));
                return;
            }

            // A new declaration in a following line means that the semicolon at the end of a line is missing.
            masked = mask(declaration);
            next = /\n\s*(--[\w-]+|-?[a-zA-Z][\w-]*)\s*:/.exec(masked);

            if (next) {
                error('semicolon', declarationLine + countLines(masked.substring(0, next.index).replace(/\s+$/, '')));
                return;
            }

            value = declaration.substring(match[0].length).trim();

            if (value === '' && match[1].indexOf('--') !== 0) {
                error('declaration', declarationLine, shorten(declaration));
            }
        }

        css = css.replace(/\r\n?/g, '\n');

        while (i < css.length) {
            ch = css[i];

            if (ch === '/' && css[i + 1] === '*') {
                end = css.indexOf('*/', i + 2);

                if (end < 0) {
                    error('comment', line);
                    return errors;
                }

                text = css.substring(i, end + 2);
                buffer = isBlank(buffer) ? '' : buffer + text;
                line += countLines(text);
                i = end + 2;
                continue;
            }

            if (ch === '"' || ch === "'") {
                end = i + 1;

                while (end < css.length && css[end] !== ch && css[end] !== '\n') {
                    end += css[end] === '\\' ? 2 : 1;
                }

                if (end >= css.length || css[end] === '\n') {
                    error('string', line);
                    return errors;
                }

                bufferLine = isBlank(buffer) ? line : bufferLine;
                buffer += css.substring(i, end + 1);
                i = end + 1;
                continue;
            }

            if (ch === '\\') {
                bufferLine = isBlank(buffer) ? line : bufferLine;
                buffer += css.substr(i, 2);
                line += css[i + 1] === '\n' ? 1 : 0;
                i += 2;
                continue;
            }

            if (ch === '(') {
                parens.push(line);
            } else if (ch === ')') {
                if (!parens.length) {
                    error('paren_close', line);
                    return errors;
                }

                parens.pop();
            } else if (!parens.length && ch === '{') {
                // Inside of brackets, e.g. url(data:image/png;base64,...), the characters have no special meaning.
                text = buffer.trim();

                if (text === '') {
                    error('selector', line);
                } else if (stack.length > 1) {
                    // Pseudo classes like a:hover have no whitespace after the colon, declarations usually have.
                    end = mask(text).indexOf('\n');

                    if (end > -1 && /^(--[\w-]+|-?[a-zA-Z][\w-]*)\s*:\s+\S/.test(mask(text).substring(0, end).trim())) {
                        error('semicolon', bufferLine);
                    }
                }

                stack.push({line: text === '' ? line : bufferLine});
                buffer = '';
                i++;
                continue;
            } else if (!parens.length && (ch === ';' || ch === '}')) {
                text = buffer.trim();

                if (text !== '') {
                    checkDeclaration(text, bufferLine);
                }

                if (ch === '}') {
                    if (stack.length < 2) {
                        error('brace_close', line);
                    } else {
                        stack.pop();
                    }
                }

                buffer = '';
                i++;
                continue;
            }

            if (ch === '\n') {
                line++;
            } else if (isBlank(buffer) && /\S/.test(ch)) {
                bufferLine = line;
            }

            buffer += ch;
            i++;
        }

        if (parens.length) {
            error('paren_open', parens[parens.length - 1]);
            return errors;
        }

        text = buffer.trim();

        if (text !== '') {
            if (stack.length < 2) {
                error(text[0] === '@' ? 'semicolon' : 'brace_open', bufferLine);
            } else {
                checkDeclaration(text, bufferLine);
            }
        }

        // Blocks, which are not closed.
        stack.slice(1).forEach(function (block) {
            error('block', block.line);
        });

        // Sort the errors by line, e.g. a block which is not closed is found at the end.
        return errors.sort(function (a, b) {
            return a.line - b.line;
        });
    }

    // Line numbers next to the field, the lines with an error are highlighted.
    function showLineNumbers() {
        var count = $css.val().split('\n').length,
            html = '',
            i;

        for (i = 1; i <= count; i++) {
            html += errorLines.indexOf(i) > -1 ? '<span class="is-error">' + i + '</span>' : '<span>' + i + '</span>';
        }

        $lines.html(html).css('width', 'calc(' + (String(count).length + 1) + 'ch + 17px)');
        $lines.scrollTop($css.scrollTop());
    }

    // Select the line in the field and scroll to it.
    function goToLine(line) {
        var textarea = $css[0],
            lines = $css.val().split('\n'),
            start = lines.slice(0, line - 1).join('\n').length + (line > 1 ? 1 : 0),
            lineHeight = parseFloat($css.css('line-height')) || 20;

        textarea.focus();
        textarea.setSelectionRange(start, start + (lines[line - 1] || '').length);
        textarea.scrollTop = Math.max(0, (line - 3) * lineHeight);
        $lines.scrollTop(textarea.scrollTop);
    }

    function showErrors() {
        var errors = checkCss($css.val());

        $check.empty();
        $code.toggleClass('has-errors', errors.length > 0);
        $css.attr('aria-invalid', errors.length > 0 ? 'true' : null);

        errorLines = errors.map(function (error) {
            return error.line;
        });

        errors.forEach(function (error) {
            var message = gaoo.css_errors[error.code] || '';

            $('<button type="button" class="gaoo-css-error">')
                .text(message.replace('%1$d', error.line).replace('%2$s', error.text))
                .on('click', function () {
                    goToLine(error.line);
                })
                .appendTo($check);
        });

        showLineNumbers();
    }

    // Highlight the classes, which are used in the CSS. Comments are ignored.
    function markUsedClasses() {
        var code = $css.val().replace(/\/\*[\s\S]*?(\*\/|$)/g, '');

        $classes.each(function () {
            var selector = $(this).data('selector').replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

            $(this).toggleClass('is-used', new RegExp(selector + '(?![\\w-])').test(code));
        });
    }

    $css.on('scroll', function () {
        $lines.scrollTop($css.scrollTop());
    });

    $css.on('input', function () {
        markUsedClasses();
        showLineNumbers();

        clearTimeout(timer);
        timer = setTimeout(showErrors, 300);
    });

    // Add an empty rule for the class at the end of the CSS, with the cursor inside of it.
    $classes.on('click', function () {
        var textarea = $css[0],
            value, caret;

        if (textarea.readOnly) {
            return;
        }

        value = $css.val().replace(/\s+$/, '');
        value = (value.length ? value + '\n\n' : '') + $(this).data('selector') + ' {\n    ';
        caret = value.length;

        $css.val(value + '\n}\n');
        textarea.focus();
        textarea.setSelectionRange(caret, caret);
        textarea.scrollTop = textarea.scrollHeight;
        $css.trigger('input');
    });

    markUsedClasses();
    showErrors();
});
