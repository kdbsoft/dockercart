/**
 * Dockercart Phone Mask
 * Vanilla JS input mask. X = digit placeholder in format string.
 *
 * Key design: user-entered digits are tracked separately from format literals
 * so formats with literal digits like "+7" or "+380" work correctly.
 *
 * Usage:
 *   DockercartPhoneMask.init(el, '+38 (XXX) XXX-XX-XX');
 *   DockercartPhoneMask.destroy(el);
 *   DockercartPhoneMask.validate('+38 (999) 123-45-67', '+38 (XXX) XXX-XX-XX');
 *   DockercartPhoneMask.getDigitCount('+38 (XXX) XXX-XX-XX'); // 10
 *   DockercartPhoneMask.stripNonDigits('+7 (999) 123-45-67'); // '389991234567'
 */

window.DockercartPhoneMask = window.DockercartPhoneMask || (() => {
  const X = "X";

  function digitCount(fmt) {
    let c = 0;
    for (let i = 0; i < fmt.length; i++) {
      if (fmt[i] === X) c++;
    }
    return c;
  }

  function stripNonDigits(v) {
    return (v || "").replace(/\D/g, "");
  }

  function pIdx(fmt) {
    const r = [];
    for (let i = 0; i < fmt.length; i++) {
      if (fmt[i] === X) r.push(i);
    }
    return r;
  }

  function leadingLen(fmt) {
    for (let i = 0; i < fmt.length; i++) {
      if (fmt[i] === X) return i;
    }
    return fmt.length;
  }

  function formatFmt(digits, fmt) {
    let s = "",
      di = 0;
    for (let i = 0; i < fmt.length; i++) {
      if (fmt[i] === X) {
        if (di < digits.length) s += digits[di++];
        else break;
      } else {
        s += fmt[i];
      }
    }
    return s;
  }

  function longestCommonPrefix(a, b) {
    let i = 0;
    while (i < a.length && i < b.length && a[i] === b[i]) i++;
    return i;
  }

  function extractUserDigits(value, fmt) {
    if (!value) return "";
    const allDigits = value.replace(/\D/g, "");
    if (!allDigits) return "";
    const firstX = fmt.indexOf(X);
    if (firstX < 0) return "";
    const prefixDigits = fmt.slice(0, firstX).replace(/\D/g, "");
    if (prefixDigits && allDigits.startsWith(prefixDigits)) {
      return allDigits.slice(prefixDigits.length);
    }
    return allDigits;
  }

  function cursorAfterN(n, fmt) {
    const p = pIdx(fmt);
    if (n <= 0) return leadingLen(fmt);
    if (n >= p.length) return p[p.length - 1] + 1;
    return p[n - 1] + 1;
  }

  function nearestDigitPos(pos, fmt) {
    const p = pIdx(fmt);
    if (p.length === 0) return pos;
    for (let i = 0; i < p.length; i++) {
      if (p[i] >= pos) return p[i];
    }
    return p[p.length - 1] + 1;
  }

  function handleKeydown(e) {
    const input = e.target;
    const fmt = input._pm_fmt;
    if (!fmt) return;
    if (input._pm_composing) return;
    if (!e.key) return;

    const p = pIdx(fmt);
    const max = digitCount(fmt);
    let digits = input._pm_digits || "";

    if (e.key === "Backspace") {
      e.preventDefault();
      const s = input.selectionStart;
      const end = input.selectionEnd;

      if (s !== end) {
        let first = -1,
          last = -1;
        for (let i = 0; i < p.length; i++) {
          if (p[i] >= s && first === -1) first = i;
          if (p[i] < end) last = i;
        }
        if (first >= 0 && last >= 0) {
          digits = digits.slice(0, first) + digits.slice(last + 1);
        } else if (first >= 0) {
          digits = digits.slice(0, first);
        }
      } else {
        let di = -1;
        for (let i = p.length - 1; i >= 0; i--) {
          if (p[i] < s) {
            di = i;
            break;
          }
        }
        if (di >= 0 && di < digits.length) {
          digits = digits.slice(0, di) + digits.slice(di + 1);
        }
      }

      input._pm_digits = digits;
      input.value = formatFmt(digits, fmt);
      let c;
      if (digits.length === 0) {
        c = leadingLen(fmt);
      } else {
        const newCursorPos = s !== end ? s : s > 0 ? s - 1 : 0;
        const finalDigits = Math.min(digits.length, p.length);
        let dBefore = 0;
        for (let i = 0; i < finalDigits; i++) {
          if (p[i] < newCursorPos) dBefore = i + 1;
        }
        c = cursorAfterN(dBefore, fmt);
      }
      input.setSelectionRange(c, c);
      return;
    }

    if (e.key === "Delete") {
      e.preventDefault();
      const s = input.selectionStart;
      const end = input.selectionEnd;

      if (s !== end) {
        let first = -1,
          last = -1;
        for (let i = 0; i < p.length; i++) {
          if (p[i] >= s && first === -1) first = i;
          if (p[i] < end) last = i;
        }
        if (first >= 0 && last >= 0) {
          digits = digits.slice(0, first) + digits.slice(last + 1);
        }
      } else {
        let di = -1;
        for (let i = 0; i < p.length; i++) {
          if (p[i] >= s) {
            di = i;
            break;
          }
        }
        if (di >= 0 && di < digits.length) {
          digits = digits.slice(0, di) + digits.slice(di + 1);
        }
      }

      input._pm_digits = digits;
      input.value = formatFmt(digits, fmt);
      const c =
        s <= input.value.length ? nearestDigitPos(s, fmt) : input.value.length;
      input.setSelectionRange(c, c);
      return;
    }

    if (
      e.key === "ArrowLeft" ||
      e.key === "ArrowRight" ||
      e.key === "Home" ||
      e.key === "End" ||
      e.key === "Tab" ||
      e.key === "Shift" ||
      e.key === "Control" ||
      e.key === "Alt" ||
      e.key === "Meta" ||
      e.key === "Escape"
    ) {
      return;
    }

    if (e.key && e.key.length === 1 && /\d/.test(e.key)) {
      e.preventDefault();
      if (digits.length >= max) {
        return;
      }

      const s = input.selectionStart;
      const end = input.selectionEnd;

      let insertIdx = 0;
      for (let i = 0; i < p.length; i++) {
        if (p[i] < s) insertIdx = i + 1;
      }

      if (s !== end) {
        let first = -1,
          last = -1;
        for (let i = 0; i < p.length; i++) {
          if (p[i] >= s && first === -1) first = i;
          if (p[i] < end) last = i;
        }
        if (first >= 0 && last >= 0) {
          digits = digits.slice(0, first) + digits.slice(last + 1);
          insertIdx = first;
        }
      }

      digits = digits.slice(0, insertIdx) + e.key + digits.slice(insertIdx);
      digits = digits.slice(0, max);
      input._pm_digits = digits;
      input.value = formatFmt(digits, fmt);

      const c = cursorAfterN(insertIdx + 1, fmt);
      input.setSelectionRange(c, c);
      return;
    }

    if (e.key.length === 1) {
      e.preventDefault();
    }
  }

  function handleInput(e) {
    const input = e.target;
    const fmt = input._pm_fmt;
    if (!fmt) return;
    if (input._pm_composing) return;
    if (input._pm_skipInput) {
      input._pm_skipInput = false;
      return;
    }

    const max = digitCount(fmt);
    const prevFormatted = formatFmt(input._pm_digits || "", fmt);

    if (input.value === prevFormatted) return;

    const extracted = extractUserDigits(input.value, fmt);
    const digits = extracted.slice(0, max);
    input._pm_digits = digits;
    input.value = formatFmt(digits, fmt);

    const c =
      digits.length > 0 ? cursorAfterN(digits.length, fmt) : leadingLen(fmt);
    input.setSelectionRange(c, c);
  }

  function handlePaste(e) {
    e.preventDefault();
    const input = e.target;
    const fmt = input._pm_fmt;
    if (!fmt) return;

    const pasted = (e.clipboardData || window.clipboardData).getData("text");
    const incoming = stripNonDigits(pasted);
    if (!incoming) return;

    const max = digitCount(fmt);
    let digits = input._pm_digits || "";
    const s = input.selectionStart;
    const end = input.selectionEnd;
    const p = pIdx(fmt);

    if (s !== end) {
      let first = -1,
        last = -1;
      for (let i = 0; i < p.length; i++) {
        if (p[i] >= s && first === -1) first = i;
        if (p[i] < end) last = i;
      }
      if (first >= 0 && last >= 0) {
        digits = digits.slice(0, first) + incoming + digits.slice(last + 1);
      } else {
        digits += incoming;
      }
    } else {
      let insertIdx = 0;
      for (let i = p.length - 1; i >= 0; i--) {
        if (p[i] < s) {
          insertIdx = i + 1;
          break;
        }
      }
      digits = digits.slice(0, insertIdx) + incoming + digits.slice(insertIdx);
    }

    digits = digits.slice(0, max);
    input._pm_digits = digits;
    input.value = formatFmt(digits, fmt);

    const c = cursorAfterN(digits.length, fmt);
    input.setSelectionRange(c, c);
  }

  function handleClick(e) {
    const input = e.target;
    const fmt = input._pm_fmt;
    if (!fmt) return;

    const c = input.selectionStart;
    if (!input.value) {
      input.setSelectionRange(leadingLen(fmt), leadingLen(fmt));
      return;
    }
    input.setSelectionRange(nearestDigitPos(c, fmt), nearestDigitPos(c, fmt));
  }

  function handleFocus(e) {
    const input = e.target;
    const fmt = input._pm_fmt;
    if (!fmt) return;

    if (!input.value) {
      setTimeout(() => {
        input.setSelectionRange(leadingLen(fmt), leadingLen(fmt));
      }, 0);
    }
  }

  function handleBlur(e) {
    const input = e.target;
    const fmt = input._pm_fmt;
    if (!fmt) return;

    if (!input.value.trim() || (input._pm_digits || "").length === 0) {
      input.value = "";
      input._pm_digits = "";
    }
  }

  function handleCompositionStart(e) {
    e.target._pm_composing = true;
  }

  function handleCompositionEnd(e) {
    const input = e.target;
    input._pm_composing = false;

    const fmt = input._pm_fmt;
    if (!fmt) return;

    const max = digitCount(fmt);
    const extracted = extractUserDigits(input.value, fmt);
    const digits = extracted.slice(0, max);
    input._pm_digits = digits;
    input.value = formatFmt(digits, fmt);
  }

  return {
    init(input, formatString) {
      if (!input || !formatString) return;
      this.destroy(input);

      input._pm_fmt = formatString;
      input._phonemask_format = formatString;
      input._pm_digits = "";

      if (input.value) {
        const extracted = extractUserDigits(input.value, formatString);
        input._pm_digits = extracted.slice(0, digitCount(formatString));
        input.value = formatFmt(input._pm_digits, formatString);
      } else {
        input.placeholder = formatString.replace(new RegExp(X, "g"), "_");
      }

      input.addEventListener("keydown", handleKeydown);
      input.addEventListener("input", handleInput);
      input.addEventListener("paste", handlePaste);
      input.addEventListener("click", handleClick);
      input.addEventListener("focus", handleFocus);
      input.addEventListener("blur", handleBlur);
      input.addEventListener("compositionstart", handleCompositionStart);
      input.addEventListener("compositionend", handleCompositionEnd);
    },

    destroy(input) {
      if (!input) return;
      input.removeEventListener("keydown", handleKeydown);
      input.removeEventListener("input", handleInput);
      input.removeEventListener("paste", handlePaste);
      input.removeEventListener("click", handleClick);
      input.removeEventListener("focus", handleFocus);
      input.removeEventListener("blur", handleBlur);
      input.removeEventListener("compositionstart", handleCompositionStart);
      input.removeEventListener("compositionend", handleCompositionEnd);
      delete input._pm_fmt;
      delete input._phonemask_format;
      delete input._pm_digits;
      delete input._pm_composing;
      delete input._pm_skipInput;
    },

    validate(value, formatString) {
      if (!formatString) return true;
      let pattern = "^";
      for (let i = 0; i < formatString.length; i++) {
        const ch = formatString[i];
        if (ch === X) pattern += "\\d";
        else pattern += ch.replace(/[-[\]{}()*+?.,\\^$|#\s]/g, "\\$&");
      }
      pattern += "$";
      return new RegExp(pattern).test(value || "");
    },

    getDigitCount: digitCount,
    stripNonDigits,
  };
})();
