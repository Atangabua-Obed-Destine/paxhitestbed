/**
 * Remove Orange Floating Banner - AGGRESSIVE REMOVAL
 */

(function() {
    'use strict';
    
    function nukeOrangeBanner() {
        console.log('🔍 Scanning for orange banner...');
        
        // Get ALL elements on the page
        const allElements = document.querySelectorAll('*');
        let removed = 0;
        
        allElements.forEach(function(el) {
            // Skip if it's a critical element
            if (el.tagName === 'HTML' || 
                el.tagName === 'HEAD' || 
                el.tagName === 'BODY' ||
                el.classList.contains('header-area') ||
                el.classList.contains('footer-bg') ||
                el.closest('.header-area') ||
                el.closest('.footer-bg') ||
                el.closest('section')) {
                return;
            }
            
            const computedStyle = window.getComputedStyle(el);
            const bgColor = computedStyle.backgroundColor;
            const position = computedStyle.position;
            const right = computedStyle.right;
            
            // Check for orange background with fixed/absolute positioning on the right
            if ((position === 'fixed' || position === 'absolute') && 
                (bgColor.includes('255, 115, 80') || 
                 bgColor.includes('255, 107, 53') ||
                 bgColor.includes('coral') ||
                 bgColor.includes('255, 99, 71'))) {
                
                console.log('🗑️ REMOVING ORANGE BANNER:', el);
                el.remove();
                removed++;
            }
            
            // Also remove any element with orange bg positioned on the right
            if (right !== 'auto' && right !== '0px' && parseInt(right) <= 100 &&
                (bgColor.includes('255, 115') || bgColor.includes('255, 107') || bgColor.includes('coral'))) {
                console.log('🗑️ REMOVING RIGHT-POSITIONED ORANGE ELEMENT:', el);
                el.remove();
                removed++;
            }
        });
        
        console.log(`✅ Removed ${removed} unwanted elements`);
    }
    
    // Run immediately
    nukeOrangeBanner();
    
    // Run on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', nukeOrangeBanner);
    }
    
    // Run multiple times with delays (in case elements are added dynamically)
    setTimeout(nukeOrangeBanner, 100);
    setTimeout(nukeOrangeBanner, 300);
    setTimeout(nukeOrangeBanner, 500);
    setTimeout(nukeOrangeBanner, 1000);
    setTimeout(nukeOrangeBanner, 2000);
    
    // Watch for new elements
    const observer = new MutationObserver(function(mutations) {
        nukeOrangeBanner();
    });
    
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
    
    console.log('🛡️ Orange banner removal script active');
    
})();
