/**
 * Print Handler for Mawared ERP
 * Handles auto-print functionality and manual print button
 */

document.addEventListener('DOMContentLoaded', () => {
    const config = window.printConfig || {};

    // Auto-print functionality
    if (config.autoPrint === true) {
        // Delay to ensure fonts and images load
        window.addEventListener('load', () => {
            setTimeout(() => {
                window.print();
            }, config.delay || 500);
        });
    }

    // Manual print button handler
    const printButton = document.querySelector('.btn-print');
    if (printButton) {
        printButton.addEventListener('click', (e) => {
            e.preventDefault();
            window.print();
        });
    }

    // Optional: Log print events for debugging
    window.addEventListener('beforeprint', () => {
        console.log('Print dialog opening...');
    });

    window.addEventListener('afterprint', () => {
        console.log('Print dialog closed');
    });
});
