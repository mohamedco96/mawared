/**
 * Force English Numerals for Display
 * Converts displayed Arabic numerals (٠١٢٣٤٥٦٧٨٩) back to Western/English digits (0123456789)
 * This ensures numbers are always displayed in English format even when locale is Arabic
 */
(function () {
    'use strict';

    // Map of Arabic/Persian digits to English digits
    const arabicNumbers = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
    const persianNumbers = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    const englishNumbers = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

    /**
     * Convert Arabic/Persian digits to English digits
     */
    function convertToEnglishDigits(text) {
        if (!text) return text;

        let result = text;

        // Replace Arabic-Indic digits
        for (let i = 0; i < 10; i++) {
            result = result.replaceAll(arabicNumbers[i], englishNumbers[i]);
        }

        // Replace Persian digits
        for (let i = 0; i < 10; i++) {
            result = result.replaceAll(persianNumbers[i], englishNumbers[i]);
        }

        return result;
    }

    /**
     * Process all text nodes in an element
     */
    function processTextNodes(element) {
        const walker = document.createTreeWalker(
            element,
            NodeFilter.SHOW_TEXT,
            null
        );

        const nodesToProcess = [];
        let node;
        while (node = walker.nextNode()) {
            nodesToProcess.push(node);
        }

        nodesToProcess.forEach(textNode => {
            const originalText = textNode.nodeValue;
            const convertedText = convertToEnglishDigits(originalText);

            if (originalText !== convertedText) {
                textNode.nodeValue = convertedText;
            }
        });
    }

    /**
     * Convert numbers in the entire document
     */
    function convertAllNumbers() {
        processTextNodes(document.body);
    }

    // Run conversion on page load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', convertAllNumbers);
    } else {
        convertAllNumbers();
    }

    // Watch for dynamic content changes (Livewire, Alpine.js, etc.)
    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            // Process added nodes
            mutation.addedNodes.forEach((node) => {
                if (node.nodeType === Node.ELEMENT_NODE) {
                    processTextNodes(node);
                } else if (node.nodeType === Node.TEXT_NODE) {
                    const originalText = node.nodeValue;
                    const convertedText = convertToEnglishDigits(originalText);
                    if (originalText !== convertedText) {
                        node.nodeValue = convertedText;
                    }
                }
            });

            // Process modified character data
            if (mutation.type === 'characterData') {
                const originalText = mutation.target.nodeValue;
                const convertedText = convertToEnglishDigits(originalText);
                if (originalText !== convertedText) {
                    mutation.target.nodeValue = convertedText;
                }
            }
        });
    });

    // Start observing the document body for changes
    observer.observe(document.body, {
        childList: true,
        subtree: true,
        characterData: true,
        characterDataOldValue: true
    });

    // Listen for Livewire updates
    if (window.Livewire) {
        document.addEventListener('livewire:update', () => {
            setTimeout(convertAllNumbers, 0);
        });
    }

    // Listen for Alpine.js updates
    document.addEventListener('alpine:initialized', () => {
        setTimeout(convertAllNumbers, 0);
    });

    // console.log('✅ English Display Enforcer: Active - All numbers will display in English format');
})();
