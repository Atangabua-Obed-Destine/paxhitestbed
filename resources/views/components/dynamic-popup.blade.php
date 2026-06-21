{{-- Dynamic Popup Component --}}
{{-- Include this component in your layout with: @include('components.dynamic-popup', ['area' => 'front_web']) --}}
{{-- Areas: all, front_web, student_portal, applicant_portal, admin_portal, login_pages --}}

@php
    $area = $area ?? 'front_web';
    $popupArea = $area;
@endphp

{{-- Dynamic Popup Modal Container --}}
<div id="dynamic-popup-container"></div>

{{-- Dynamic Popup Styles --}}
<style>
    .dynamic-popup-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.6);
        z-index: 99999;
        display: flex;
        justify-content: center;
        align-items: center;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
    }
    
    .dynamic-popup-overlay.active {
        opacity: 1;
        visibility: visible;
    }
    
    .dynamic-popup-modal {
        background: #fff;
        border-radius: 12px;
        max-width: 480px;
        width: 90%;
        max-height: 90vh;
        overflow: hidden;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        transform: scale(0.8) translateY(20px);
        transition: all 0.3s ease;
    }
    
    .dynamic-popup-overlay.active .dynamic-popup-modal {
        transform: scale(1) translateY(0);
    }
    
    .dynamic-popup-close {
        position: absolute;
        top: 10px;
        right: 10px;
        width: 32px;
        height: 32px;
        background: rgba(255, 255, 255, 0.9);
        border: none;
        border-radius: 50%;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10;
        transition: all 0.2s ease;
        font-size: 18px;
        color: #333;
    }
    
    .dynamic-popup-close:hover {
        background: #fff;
        transform: scale(1.1);
    }
    
    .dynamic-popup-image-wrapper {
        position: relative;
        width: 100%;
        max-height: 300px;
        overflow: hidden;
    }
    
    .dynamic-popup-image {
        width: 100%;
        height: auto;
        display: block;
    }
    
    .dynamic-popup-no-image {
        width: 100%;
        height: 150px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .dynamic-popup-no-image i {
        font-size: 48px;
        color: rgba(255, 255, 255, 0.5);
    }
    
    .dynamic-popup-content {
        padding: 24px;
    }
    
    .dynamic-popup-title {
        font-size: 24px;
        font-weight: 700;
        color: #1a1a1a;
        margin: 0 0 12px 0;
        line-height: 1.3;
    }
    
    .dynamic-popup-summary {
        font-size: 15px;
        color: #555;
        line-height: 1.6;
        margin: 0 0 20px 0;
    }
    
    .dynamic-popup-button {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 24px;
        font-size: 15px;
        font-weight: 600;
        border-radius: 6px;
        text-decoration: none;
        transition: all 0.2s ease;
    }
    
    .dynamic-popup-button:hover {
        opacity: 0.9;
        transform: translateY(-2px);
        text-decoration: none;
    }
    
    .dynamic-popup-button i {
        font-size: 12px;
    }
    
    /* Position variations */
    .dynamic-popup-overlay.position-top-left {
        align-items: flex-start;
        justify-content: flex-start;
        padding: 20px;
    }
    
    .dynamic-popup-overlay.position-top-right {
        align-items: flex-start;
        justify-content: flex-end;
        padding: 20px;
    }
    
    .dynamic-popup-overlay.position-bottom-left {
        align-items: flex-end;
        justify-content: flex-start;
        padding: 20px;
    }
    
    .dynamic-popup-overlay.position-bottom-right {
        align-items: flex-end;
        justify-content: flex-end;
        padding: 20px;
    }
    
    /* Mobile responsive */
    @media (max-width: 576px) {
        .dynamic-popup-modal {
            max-width: 95%;
        }
        
        .dynamic-popup-content {
            padding: 20px;
        }
        
        .dynamic-popup-title {
            font-size: 20px;
        }
        
        .dynamic-popup-overlay.position-top-left,
        .dynamic-popup-overlay.position-top-right,
        .dynamic-popup-overlay.position-bottom-left,
        .dynamic-popup-overlay.position-bottom-right {
            justify-content: center;
            padding: 10px;
        }
    }
</style>

{{-- Dynamic Popup Scripts --}}
<script>
(function() {
    // Configuration
    const POPUP_AREA = '{{ $popupArea }}';
    const API_BASE = '{{ url("/api") }}';
    
    // State
    let popupQueue = [];
    let currentPopupIndex = 0;
    let dismissedInSession = JSON.parse(sessionStorage.getItem('dismissedPopups') || '[]');
    let viewedInSession = JSON.parse(sessionStorage.getItem('viewedPopups') || '[]');
    
    // Load popups when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        loadPopups();
    });
    
    // Load popups from API
    function loadPopups() {
        fetch(`${API_BASE}/popups/${POPUP_AREA}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.popups && data.popups.length > 0) {
                    // Filter out dismissed and viewed popups based on frequency
                    popupQueue = data.popups.filter(popup => {
                        // Check frequency rules first
                        switch (popup.display_frequency) {
                            case 'always':
                                // Always show on every page load, ignore dismissals
                                return true;
                            case 'once_session':
                                // Check if dismissed or already viewed in this session
                                if (dismissedInSession.includes(popup.id)) {
                                    return false;
                                }
                                return !viewedInSession.includes(popup.id);
                            case 'once_day':
                                // Check if dismissed in session or already viewed today
                                if (dismissedInSession.includes(popup.id)) {
                                    return false;
                                }
                                return !wasViewedToday(popup.id);
                            case 'once_ever':
                                // Check if dismissed in session or ever viewed
                                if (dismissedInSession.includes(popup.id)) {
                                    return false;
                                }
                                return !wasViewedEver(popup.id);
                            default:
                                return true;
                        }
                    });
                    
                    // Sort by priority (higher first), then by pinned status
                    popupQueue.sort((a, b) => {
                        if (a.is_pinned !== b.is_pinned) {
                            return b.is_pinned ? 1 : -1;
                        }
                        return b.priority - a.priority;
                    });
                    
                    // Show first popup if queue is not empty
                    if (popupQueue.length > 0) {
                        showPopup(popupQueue[0]);
                    }
                }
            })
            .catch(error => {
                console.error('Error loading dynamic popups:', error);
            });
    }
    
    // Show a popup
    function showPopup(popup) {
        const container = document.getElementById('dynamic-popup-container');
        if (!container) return;
        
        // Build popup HTML
        const positionClass = getPositionClass(popup.popup_position);
        const buttonTextColor = popup.button_text_color === 'light' ? '#fff' : '#000';
        
        let imageHtml = '';
        if (popup.image) {
            imageHtml = `
                <div class="dynamic-popup-image-wrapper">
                    <img src="${popup.image}" alt="${popup.title}" class="dynamic-popup-image">
                </div>
            `;
        } else {
            imageHtml = `
                <div class="dynamic-popup-no-image">
                    <i class="fas fa-bullhorn"></i>
                </div>
            `;
        }
        
        let buttonHtml = '';
        if (popup.button_text) {
            buttonHtml = `
                <a href="${popup.link || '#'}" 
                   class="dynamic-popup-button" 
                   style="background-color: ${popup.button_color}; color: ${buttonTextColor};"
                   ${popup.link ? 'target="_blank"' : ''}>
                    ${popup.button_text}
                    <i class="fas fa-arrow-right"></i>
                </a>
            `;
        }
        
        let closeButton = '';
        if (popup.is_dismissible) {
            closeButton = `<button type="button" class="dynamic-popup-close" onclick="closeDynamicPopup(${popup.id})">&times;</button>`;
        }
        
        const html = `
            <div class="dynamic-popup-overlay ${positionClass}" id="dynamic-popup-${popup.id}">
                <div class="dynamic-popup-modal">
                    ${closeButton}
                    ${imageHtml}
                    <div class="dynamic-popup-content">
                        <h3 class="dynamic-popup-title">${popup.title}</h3>
                        ${popup.summary ? `<p class="dynamic-popup-summary">${popup.summary}</p>` : ''}
                        ${buttonHtml}
                    </div>
                </div>
            </div>
        `;
        
        container.innerHTML = html;
        
        // Animate in
        setTimeout(() => {
            const overlay = document.getElementById(`dynamic-popup-${popup.id}`);
            if (overlay) {
                overlay.classList.add('active');
            }
        }, 100);
        
        // Mark as viewed
        markAsViewed(popup.id, popup.display_frequency);
        
        // Click outside to close (if dismissible)
        if (popup.is_dismissible) {
            const overlay = document.getElementById(`dynamic-popup-${popup.id}`);
            if (overlay) {
                overlay.addEventListener('click', function(e) {
                    if (e.target === overlay) {
                        closeDynamicPopup(popup.id);
                    }
                });
            }
        }
    }
    
    // Close popup and show next in queue
    window.closeDynamicPopup = function(popupId) {
        const overlay = document.getElementById(`dynamic-popup-${popupId}`);
        if (overlay) {
            overlay.classList.remove('active');
            
            setTimeout(() => {
                overlay.remove();
                
                // Mark as dismissed for this session
                dismissedInSession.push(popupId);
                sessionStorage.setItem('dismissedPopups', JSON.stringify(dismissedInSession));
                
                // Call dismiss API
                fetch(`${API_BASE}/popups/${popupId}/dismiss`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                    }
                }).catch(() => {});
                
                // Show next popup in queue
                currentPopupIndex++;
                if (currentPopupIndex < popupQueue.length) {
                    setTimeout(() => {
                        showPopup(popupQueue[currentPopupIndex]);
                    }, 500);
                }
            }, 300);
        }
    };
    
    // Helper functions
    function getPositionClass(position) {
        const positions = {
            'center': '',
            'top-left': 'position-top-left',
            'top-right': 'position-top-right',
            'bottom-left': 'position-bottom-left',
            'bottom-right': 'position-bottom-right'
        };
        return positions[position] || '';
    }
    
    function markAsViewed(popupId, frequency) {
        // Session storage
        if (!viewedInSession.includes(popupId)) {
            viewedInSession.push(popupId);
            sessionStorage.setItem('viewedPopups', JSON.stringify(viewedInSession));
        }
        
        // Local storage for day/ever frequency
        if (frequency === 'once_day') {
            const viewedToday = JSON.parse(localStorage.getItem('viewedPopupsToday') || '{}');
            viewedToday[popupId] = new Date().toDateString();
            localStorage.setItem('viewedPopupsToday', JSON.stringify(viewedToday));
        } else if (frequency === 'once_ever') {
            const viewedEver = JSON.parse(localStorage.getItem('viewedPopupsEver') || '[]');
            if (!viewedEver.includes(popupId)) {
                viewedEver.push(popupId);
                localStorage.setItem('viewedPopupsEver', JSON.stringify(viewedEver));
            }
        }
    }
    
    function wasViewedToday(popupId) {
        const viewedToday = JSON.parse(localStorage.getItem('viewedPopupsToday') || '{}');
        return viewedToday[popupId] === new Date().toDateString();
    }
    
    function wasViewedEver(popupId) {
        const viewedEver = JSON.parse(localStorage.getItem('viewedPopupsEver') || '[]');
        return viewedEver.includes(popupId);
    }
})();
</script>
