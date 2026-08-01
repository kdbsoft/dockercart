/**
 * DockerCart Search - Voice Search (Web Speech API)
 * Fills the catalog search input from speech recognition.
 *
 * @package    DockerCart
 * @subpackage Module
 * @author     DockerCart Official
 * @copyright  2026 DockerCart
 * @license    MIT
 * @version    1.0.0
 */

(function() {
    'use strict';

    var SpeechRecognitionCtor = window.SpeechRecognition || window.webkitSpeechRecognition;

    // Hide the buttons before first paint when the API is unavailable
    // (insecure context / unsupported browser). Runs at parse time in <head>,
    // so the button never flashes on the page.
    if (!SpeechRecognitionCtor || window.isSecureContext === false) {
        var style = document.createElement('style');
        style.textContent = '.dc-voice-search-btn{display:none}';
        document.head.appendChild(style);
        return;
    }

    var buttons = [];
    var recognition = null;
    var activeButton = null;
    var originalTitle = '';
    var listeningTitle = '';

    function init() {
        buttons = Array.prototype.slice.call(document.querySelectorAll('.dc-voice-search-btn'));

        if (!buttons.length) { return; }

        injectStyles();

        buttons.forEach(function(btn) {
            btn.addEventListener('click', function() { handleClick(btn); });
        });

        document.addEventListener('submit', function(e) {
            if (e.target && e.target.querySelector && e.target.querySelector('input[name="search"]')) {
                stopListening();
            }
        }, true);
    }

    function handleClick(btn) {
        if (recognition && activeButton) {
            stopListening();
            return;
        }

        var form = btn.closest('form');
        var input = form ? form.querySelector('input[name="search"]') : null;

        if (!input) { return; }

        listeningTitle = btn.getAttribute('data-voice-listening-title') || btn.title || '';
        originalTitle = btn.title;
        activeButton = btn;
        btn.classList.add('dc-voice-btn-active');
        btn.style.background = '#fee2e2';
        if (listeningTitle) { btn.title = listeningTitle; }

        recognition = new SpeechRecognitionCtor();
        recognition.lang = getRecognitionLang();
        recognition.interimResults = false;
        recognition.maxAlternatives = 1;

        recognition.onresult = function(event) {
            var transcript = '';
            for (var i = 0; i < event.results.length; i++) {
                if (event.results[i].isFinal) {
                    transcript += event.results[i][0].transcript;
                }
            }
            applyTranscript(transcript, input);
        };

        recognition.onerror = function(event) {
            if (event.error !== 'aborted') {
                console.warn('DockerCart Voice Search error:', event.error);
            }
        };

        recognition.onend = function() {
            resetButton();
        };

        try {
            recognition.start();
        } catch (e) {
            resetButton();
            console.warn('DockerCart Voice Search start error:', e);
        }
    }

    function stopListening() {
        if (!recognition) { return; }
        recognition.onend = null;
        try { recognition.abort(); } catch (e) {}
        recognition = null;
        resetButton();
    }

    function resetButton() {
        if (!activeButton) { return; }
        activeButton.classList.remove('dc-voice-btn-active');
        activeButton.style.background = '';
        activeButton.title = originalTitle;
        activeButton = null;
        originalTitle = '';
        listeningTitle = '';
    }

    function applyTranscript(transcript, input) {
        if (!transcript || !input) { return; }
        input.value = transcript.trim();
        input.focus();
        try { input.setSelectionRange(input.value.length, input.value.length); } catch (e) {}
        input.dispatchEvent(new Event('input', { bubbles: true }));
    }

    function getRecognitionLang() {
        var lang = document.documentElement.getAttribute('lang');
        if (lang && /^[a-z]{2}([-_][a-zA-Z]{2,4})?$/i.test(lang)) {
            return lang.replace('_', '-');
        }
        return (navigator.language || 'en-US').replace('_', '-');
    }

    function injectStyles() {
        var style = document.createElement('style');
        style.textContent = [
            '.dc-voice-btn-active{color:#dc2626!important}',
            '.dc-voice-btn-active svg{animation:dc-voice-pulse 1.2s ease-in-out infinite}',
            '@keyframes dc-voice-pulse{0%,100%{opacity:1}50%{opacity:.3}}'
        ].join('');
        document.head.appendChild(style);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
