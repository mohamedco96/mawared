/**
 * Global Arabic/Eastern Digit to English Digit Converter
 * Automatically converts Eastern Arabic numerals (٠١٢٣٤٥٦٧٨٩) to Western/English digits (0123456789)
 * This allows users with Arabic keyboard layouts to type numbers without switching layouts
 */
(function () {
    'use strict';

    // Debug mode - set to true to see console logs
    const DEBUG = false;

    // Map of Arabic/Persian digits to English digits
    const arabicNumbers = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
    const persianNumbers = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    const englishNumbers = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

    /**
     * Convert Arabic/Persian digits to English digits
     */
    function convertToEnglishDigits(value) {
        if (!value) return value;

        let newValue = value;

        // Replace Arabic-Indic digits (Eastern Arabic)
        for (let i = 0; i < 10; i++) {
            const arabicRegex = new RegExp(arabicNumbers[i], 'g');
            newValue = newValue.replace(arabicRegex, englishNumbers[i]);
        }

        // Replace Persian/Farsi digits
        for (let i = 0; i < 10; i++) {
            const persianRegex = new RegExp(persianNumbers[i], 'g');
            newValue = newValue.replace(persianRegex, englishNumbers[i]);
        }

        return newValue;
    }

    /**
     * Handle input events on numeric fields
     */
    function handleInput(e) {
        const input = e.target;

        // Only process input elements
        if (input.tagName !== 'INPUT') return;

        // Check if it's a numeric field (has numeric type, inputmode decimal, or dir ltr)
        const isNumericField =
            input.type === 'number' ||
            input.getAttribute('inputmode') === 'decimal' ||
            input.getAttribute('inputmode') === 'numeric' ||
            (input.type === 'text' && input.getAttribute('dir') === 'ltr');

        if (!isNumericField) return;

        const originalValue = input.value;
        const newValue = convertToEnglishDigits(originalValue);

        // Only update if value actually changed
        if (originalValue !== newValue) {
            // Get cursor position before update
            const cursorPosition = input.selectionStart;

            // Update value
            input.value = newValue;

            // Restore cursor position (only for text inputs, not number)
            if (input.type !== 'number' && cursorPosition !== null) {
                input.setSelectionRange(cursorPosition, cursorPosition);
            }

            // Trigger change event for Livewire/Filament reactivity
            // For number inputs, we need to trigger both input and change events
            if (input.type === 'number') {
                const changeEvent = new Event('change', { bubbles: true });
                input.dispatchEvent(changeEvent);
            }
        }
    }

    /**
     * Handle keydown events to intercept Arabic digit keys BEFORE they're entered
     * This is especially important for type="number" inputs that reject Arabic digits
     */
    function handleKeydown(e) {
        const input = e.target;

        // Only process input elements
        if (input.tagName !== 'INPUT') return;

        // Check if it's a numeric field
        const isNumericField =
            input.type === 'number' ||
            input.getAttribute('inputmode') === 'decimal' ||
            input.getAttribute('inputmode') === 'numeric' ||
            (input.type === 'text' && input.getAttribute('dir') === 'ltr');

        if (!isNumericField) return;

        // Get the pressed key
        const key = e.key;

        // Check if it's an Arabic or Persian digit
        const arabicIndex = arabicNumbers.indexOf(key);
        const persianIndex = persianNumbers.indexOf(key);

        if (arabicIndex !== -1) {
            if (DEBUG) console.log('🔢 Arabic digit detected:', key, '→', englishNumbers[arabicIndex]);
            // Prevent default and stop propagation to block other handlers
            e.preventDefault();
            e.stopImmediatePropagation();
            insertTextAtCursor(input, englishNumbers[arabicIndex]);
        } else if (persianIndex !== -1) {
            if (DEBUG) console.log('🔢 Persian digit detected:', key, '→', englishNumbers[persianIndex]);
            // Prevent default and stop propagation to block other handlers
            e.preventDefault();
            e.stopImmediatePropagation();
            insertTextAtCursor(input, englishNumbers[persianIndex]);
        }
    }

    /**
     * Insert text at cursor position, respecting text selection
     */
    function insertTextAtCursor(input, text) {
        // Focus the input first to ensure we have the correct selection state
        input.focus();

        if (DEBUG) {
            console.log('insertTextAtCursor:', {
                type: input.type,
                value: input.value,
                selectionStart: input.selectionStart,
                selectionEnd: input.selectionEnd,
                hasSetRangeText: typeof input.setRangeText === 'function'
            });
        }

        // Special handling for type="number" - it doesn't support selection/cursor position
        if (input.type === 'number') {
            // For type="number", we can't determine cursor position
            // Best we can do is append to the end (most natural for typing)
            const currentValue = input.value || '';
            input.value = currentValue + text;

            if (DEBUG) console.log('type="number" - appended to end:', input.value);

            // Trigger events for reactivity
            triggerInputEvents(input);
            return;
        }

        // For all other input types, try modern setRangeText API first
        if (typeof input.setRangeText === 'function') {
            try {
                const start = input.selectionStart;
                const end = input.selectionEnd;

                if (DEBUG) console.log('Using setRangeText, start:', start, 'end:', end);

                // setRangeText replaces the current selection or inserts at cursor
                // 'end' parameter moves cursor to end of inserted text
                input.setRangeText(text, start, end, 'end');

                if (DEBUG) console.log('setRangeText succeeded, new value:', input.value);

                // Trigger events for reactivity
                triggerInputEvents(input);
                return;
            } catch (e) {
                // setRangeText failed, fall back to manual insertion
                if (DEBUG) console.log('setRangeText failed, using fallback:', e);
            }
        }

        // Fallback: Manual text insertion for text inputs
        const start = input.selectionStart ?? 0;
        const end = input.selectionEnd ?? 0;
        const currentValue = input.value;

        if (DEBUG) console.log('Using fallback, start:', start, 'end:', end, 'currentValue:', currentValue);

        // Replace selected text (or insert at cursor if no selection)
        const newValue = currentValue.substring(0, start) + text + currentValue.substring(end);
        input.value = newValue;

        if (DEBUG) console.log('Fallback set value to:', newValue);

        // Set cursor after inserted text
        const newCursorPos = start + text.length;

        try {
            input.setSelectionRange(newCursorPos, newCursorPos);
        } catch (e) {
            // Some input types don't support selection
            if (DEBUG) console.log('setSelectionRange failed:', e);
        }

        // Trigger input event for reactivity
        triggerInputEvents(input);
    }

    /**
     * Trigger all necessary events for Livewire/Alpine.js/Filament reactivity
     */
    function triggerInputEvents(input) {
        // Trigger input event (for most frameworks)
        const inputEvent = new Event('input', { bubbles: true, cancelable: true });
        input.dispatchEvent(inputEvent);

        // Trigger change event (for some legacy handlers)
        const changeEvent = new Event('change', { bubbles: true, cancelable: true });
        input.dispatchEvent(changeEvent);

        // Trigger Alpine.js/Livewire events if they exist
        if (window.Livewire) {
            // Force Livewire to recognize the change
            input.dispatchEvent(new Event('input', { bubbles: true }));
        }
    }

    // Attach event listeners to document for all current and future inputs (event delegation)
    // Use capture phase (true) to ensure our handler runs before Filament's handlers
    document.addEventListener('keydown', handleKeydown, true);
    document.addEventListener('input', handleInput, true);

    // Also listen for Livewire component updates to handle dynamically added inputs
    if (window.Livewire) {
        document.addEventListener('livewire:update', function () {
            if (DEBUG) console.log('Livewire updated - converter still active');
        });
    }

    // Listen for Alpine.js component initialization
    document.addEventListener('alpine:init', function () {
        if (DEBUG) console.log('Alpine.js initialized - converter active');
    });

    // Also handle paste events
    document.addEventListener('paste', function (e) {
        const input = e.target;

        if (input.tagName !== 'INPUT') return;

        const isNumericField =
            input.type === 'number' ||
            input.getAttribute('inputmode') === 'decimal' ||
            input.getAttribute('inputmode') === 'numeric' ||
            (input.type === 'text' && input.getAttribute('dir') === 'ltr');

        if (!isNumericField) return;

        // Get pasted data
        const pastedData = (e.clipboardData || window.clipboardData).getData('text');
        const convertedData = convertToEnglishDigits(pastedData);

        // If conversion changed the data, prevent default and insert converted text
        if (pastedData !== convertedData) {
            e.preventDefault();

            // Insert converted text at cursor position
            const start = input.selectionStart;
            const end = input.selectionEnd;
            const currentValue = input.value;

            input.value = currentValue.substring(0, start) + convertedData + currentValue.substring(end);

            // Set cursor after inserted text
            const newCursorPos = start + convertedData.length;
            input.setSelectionRange(newCursorPos, newCursorPos);

            // Trigger events for reactivity
            triggerInputEvents(input);
        }
    }, true);

    // Arabic digit converter initialized successfully
    console.log('✅ Arabic Numbers Converter: Active - All numeric fields ready');
})(); // Execute immediately, don't wait for DOMContentLoaded
