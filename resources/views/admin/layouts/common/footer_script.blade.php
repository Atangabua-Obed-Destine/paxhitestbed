    <!-- Required Js -->
    <script src="{{ asset('dashboard/plugins/jquery/js/jquery.min.js') }}"></script>
    <script src="{{ asset('dashboard/plugins/popper/js/popper.min.js') }}"></script>
    <script src="{{ asset('dashboard/plugins/bootstrap/js/bootstrap.min.js') }}"></script>
    <script src="{{ asset('dashboard/plugins/jquery-scrollbar/js/perfect-scrollbar.min.js') }}"></script>
    <script src="{{ asset('dashboard/js/pcoded.min.js') }}"></script>

    <!-- datatable Js -->
    <script src="{{ asset('dashboard/plugins/data-tables/js/datatables.min.js') }}"></script>

    <!-- form-validation Js -->
    <script src="{{ asset('dashboard/js/pages/form-validation.js') }}"></script>

    <!-- select2 Js -->
    <script src="{{ asset('dashboard/plugins/select2/js/select2.full.min.js') }}"></script>

    <!-- material datetimepicker Js -->
    <script src="{{ asset('dashboard/plugins/moment/js/moment-with-locales.min.js') }}"></script>
    <script src="{{ asset('dashboard/plugins/material-datetimepicker/js/bootstrap-material-datetimepicker.js') }}"></script>

    <!-- Input mask Js -->
    <script src="{{ asset('dashboard/plugins/inputmask/js/autoNumeric.js') }}"></script>

    <!-- minicolors Js -->
    <script src="{{ asset('dashboard/plugins/mini-color/js/jquery.minicolors.min.js') }}"></script>

    <!-- toastr Js -->
    <script src="{{ asset('dashboard/plugins/toastr/js/toastr.min.js') }}"></script>
    <!-- Toastr message display -->

    <script type="text/javascript">
        @if($errors->any())
            @foreach($errors->all() as $error)
                toastr["error"]("{{ $error }}");
            @endforeach
        @endif
    </script>

    <!-- Print Js -->
    <script src="{{ asset('dashboard/plugins/print/js/jQuery.print.min.js') }}"></script>
    <script type="text/javascript">
    $(function() {
      "use strict";
      $("html").find('.btn-print').on('click', function() {
        $.print(".printable");
      });
    });
    </script>

    <!-- Popup Window Js -->
    <script type="text/javascript">
        "use strict";
        function PopupWin(pageURL, pageTitle, popupWinWidth, popupWinHeight) {
            var left = (screen.width - popupWinWidth) / 2;
            var top = (screen.height - popupWinHeight) / 4;

            var myWindow = window.open(pageURL, pageTitle, 'resizable=yes, width=' + popupWinWidth + ', height=' + popupWinHeight + ', top=' + top + ', left=' + left);
        };
    </script>


    <!-- page js -->
    @yield('page_js')


    <script type="text/javascript">
        'use strict';
        $(document).ready(function() {
            // [ Single Select ] start
            $(".select2").select2();

            // [ Multi Select ] start
            $(".select2-multiple").select2({
                placeholder: "{{ __('select') }}"
            });

            // Date Picker
            $('.date').bootstrapMaterialDatePicker({
                setDate: new Date(),
                weekStart: 0,
                time: false
            });

            // Time Picker
            $('.time').bootstrapMaterialDatePicker({
                date: false,
                shortTime: true,
                format: 'HH:mm'
            });

            // Color Picker
            $('.color_picker').each(function() {
                $(this).minicolors({
                    control: $(this).attr('data-control') || 'hue',
                    defaultValue: $(this).attr('data-defaultValue') || '',
                    format: $(this).attr('data-format') || 'hex',
                    keywords: $(this).attr('data-keywords') || '',
                    inline: $(this).attr('data-inline') === 'true',
                    letterCase: $(this).attr('data-letterCase') || 'lowercase',
                    opacity: $(this).attr('data-opacity'),
                    position: $(this).attr('data-position') || 'bottom',
                    swatches: $(this).attr('data-swatches') ? $(this).attr('data-swatches').split('|') : [],
                    change: function(value, opacity) {
                        if (!value) return;
                        if (opacity) value += ', ' + opacity;
                        if (typeof console === 'object') {
                        }
                    },
                    theme: 'bootstrap'
                });
            });

            // Number Mask - only initialize if elements exist
            if ($('.autonumber').length > 0) {
                new AutoNumeric('.autonumber', {
                    minimumValue : '0',
                    maximumValue : '999999999',
                    decimalPlaces : 0,
                    decimalCharacter : '.',
                    digitGroupSeparator : '',
                });
            }
        });
    </script>

    <script type="text/javascript">
        'use strict';
        $(document).ready(function() {
            // [ Zero-configuration ] start
            $('#basic-table').DataTable();
            $('#basic-table2').DataTable();

            // [ HTML5-Export ] start
            $('#export-table').DataTable({
                dom: 'Bfrtip',
                buttons: [
                    {
                        extend: 'copyHtml5',
                        text: '<i class="fas fa-copy"></i>',
                        footer: true,
                        exportOptions: {
                            columns: ':visible:not(:last-child)',
                        }
                    },
                    {
                        extend: 'excelHtml5',
                        text: '<i class="fas fa-file-excel"></i>',
                        footer: true,
                        exportOptions: {
                            columns: ':visible:not(:last-child)',
                        }
                    },
                    {
                        extend: 'csvHtml5',
                        text: '<i class="fas fa-file"></i>',
                        footer: true,
                        exportOptions: {
                            columns: ':visible:not(:last-child)',
                        }
                    },
                    {
                        extend: 'pdfHtml5',
                        text: '<i class="fas fa-file-pdf"></i>',
                        footer: true,
                        exportOptions: {
                            columns: ':visible:not(:last-child)',
                        }
                    },
                    {
                        extend: 'print',
                        text: '<i class="fas fa-print"></i>',
                        autoPrint: true,
                        // title: '',
                        footer: true,
                        exportOptions: {
                            columns: ':visible:not(:last-child)',
                        },
                        customize: function ( win ) {
                            $(win.document.body)
                                .css( 'font-size', '10pt' )
                                /*.prepend(
                                    '<img src="http://datatables.net/media/images/logo-fade.png" style="position:absolute; top:0; left:0;" />'
                                );*/

                            $(win.document.body).find( 'table' )
                                .addClass( 'compact' )
                                .css( 'font-size', 'inherit' );

                            $(win.document.body).find( 'caption' )
                                .css( 'font-size', '10px' );

                            $(win.document.body).find('h1')
                                .css({"text-align": "center", "font-size": "16pt"});
                        }
                    }
                ]
            });
        });
    </script>

    {{-- Set Cookie --}}
    <script type="text/javascript">
        "use strict";
        $(document).ready(function(){
            $("#mobile-collapse").on( "click", function(e) {
               e.preventDefault();
               $.ajaxSetup({
                  headers: {
                      'X-CSRF-TOKEN': $('meta[name="_token"]').attr('content')
                  }
              });
            $.ajax({
               url: "{{ route('setCookie') }}",
               method: 'get',
               data: {},
               success: function(result){
                  console.log(result.data);
               }});
            });
        });
    </script>

    {{-- Sidebar Overlay for Mobile --}}
    <script type="text/javascript">
        "use strict";
        $(document).ready(function(){
            var $overlay = $('#sidebarOverlay');
            var $navbar = $('.pcoded-navbar');

            // Watch for mob-open class on sidebar
            var observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(m) {
                    if ($navbar.hasClass('mob-open')) {
                        $overlay.addClass('active');
                    } else {
                        $overlay.removeClass('active');
                    }
                });
            });
            if ($navbar.length) {
                observer.observe($navbar[0], { attributes: true, attributeFilter: ['class'] });
            }

            // Close sidebar when clicking anywhere outside it (overlay, header, main content)
            $(document).on('click touchstart', function(e) {
                if (!$navbar.hasClass('mob-open')) return;
                var $target = $(e.target);
                // Ignore clicks inside the sidebar or on the hamburger toggle
                if ($target.closest('.pcoded-navbar').length ||
                    $target.closest('#mobile-collapse1').length ||
                    $target.closest('.mobile-menu').length) return;
                e.stopPropagation();
                e.preventDefault();
                closeSidebar();
            });

            function closeSidebar() {
                $overlay.removeClass('active');
                $navbar.removeClass('mob-open');
                // Reset the framework's hamburger toggle state without triggering it
                $navbar.removeClass('navbar-collapsed');
                $('body').removeClass('sidebar-open');
            }
        });
    </script>


    {{-- Text Editors --}}
    <script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/5.10.3/tinymce.min.js"></script>

    @php
    $version = App\Models\Language::version();
    @endphp
    @if($version->direction == 1)
    <script type="text/javascript">
      "use strict";
      tinymce.init({
        selector: '.texteditor',

        height: 400,
        menubar: true,
        plugins: [
          'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
          'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
          'insertdatetime', 'media', 'table', 'help', 'wordcount', 'paste'
        ],
        toolbar: 'undo redo | blocks | bold italic underline strikethrough | forecolor backcolor | ' +
          'alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | ' +
          'removeformat | link image media table | code fullscreen preview | help',
        content_style: 'html { background: #eef1f6; } body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; font-size: 14px; line-height: 1.6; max-width: 720px; margin: 16px auto; padding: 26px 30px; background: #fff; box-shadow: 0 0 6px rgba(0,0,0,.14); } body img { max-width: 100%; height: auto; } body table { max-width: 100%; }',
        block_formats: 'Paragraph=p; Heading 1=h1; Heading 2=h2; Heading 3=h3; Heading 4=h4; Heading 5=h5; Heading 6=h6; Preformatted=pre',
        fontsize_formats: '8pt 10pt 12pt 14pt 16pt 18pt 24pt 36pt 48pt',
        font_formats: 'Arial=arial,helvetica,sans-serif; Courier New=courier new,courier,monospace; Georgia=georgia,palatino; Tahoma=tahoma,arial,helvetica,sans-serif; Times New Roman=times new roman,times; Verdana=verdana,geneva',
        image_advtab: true,
        
        // Enable image paste and drag-drop upload
        paste_data_images: true,
        automatic_uploads: true,

        // Retain inline formatting (fonts, sizes, colours, alignment) when pasting from Word / Google Docs
        paste_remove_styles_if_webkit: false,
        paste_webkit_styles: 'all',
        paste_retain_style_properties: 'all',
        paste_merge_formats: false,
        images_upload_url: '{{ route("admin.editor.upload-image") }}',
        images_upload_credentials: true,
        images_upload_handler: function (blobInfo, success, failure) {
            var xhr, formData;
            xhr = new XMLHttpRequest();
            xhr.withCredentials = true;
            xhr.open('POST', '{{ route("admin.editor.upload-image") }}');
            xhr.setRequestHeader('X-CSRF-TOKEN', '{{ csrf_token() }}');
            xhr.onload = function() {
                var json;
                if (xhr.status != 200) {
                    failure('HTTP Error: ' + xhr.status);
                    return;
                }
                json = JSON.parse(xhr.responseText);
                if (!json || typeof json.location != 'string') {
                    failure('Invalid JSON: ' + xhr.responseText);
                    return;
                }
                success(json.location);
            };
            xhr.onerror = function () {
                failure('Image upload failed due to a network error');
            };
            formData = new FormData();
            formData.append('file', blobInfo.blob(), blobInfo.filename());
            formData.append('_token', '{{ csrf_token() }}');
            xhr.send(formData);
        },
        
        setup: function (editor) {
            editor.on('init change', function () {
                editor.save();
            });
        },

        directionality : 'rtl',
        language: '{{ $version->code }}',
      });
    </script>
    @else
    <script type="text/javascript">
      "use strict";
      tinymce.init({
        selector: '.texteditor',

        height: 400,
        menubar: true,
        plugins: [
          'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
          'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
          'insertdatetime', 'media', 'table', 'help', 'wordcount', 'paste'
        ],
        toolbar: 'undo redo | blocks | bold italic underline strikethrough | forecolor backcolor | ' +
          'alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | ' +
          'removeformat | link image media table | code fullscreen preview | help',
        content_style: 'html { background: #eef1f6; } body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; font-size: 14px; line-height: 1.6; max-width: 720px; margin: 16px auto; padding: 26px 30px; background: #fff; box-shadow: 0 0 6px rgba(0,0,0,.14); } body img { max-width: 100%; height: auto; } body table { max-width: 100%; }',
        block_formats: 'Paragraph=p; Heading 1=h1; Heading 2=h2; Heading 3=h3; Heading 4=h4; Heading 5=h5; Heading 6=h6; Preformatted=pre',
        fontsize_formats: '8pt 10pt 12pt 14pt 16pt 18pt 24pt 36pt 48pt',
        font_formats: 'Arial=arial,helvetica,sans-serif; Courier New=courier new,courier,monospace; Georgia=georgia,palatino; Tahoma=tahoma,arial,helvetica,sans-serif; Times New Roman=times new roman,times; Verdana=verdana,geneva',
        image_advtab: true,
        
        // Enable image paste and drag-drop upload
        paste_data_images: true,
        automatic_uploads: true,

        // Retain inline formatting (fonts, sizes, colours, alignment) when pasting from Word / Google Docs
        paste_remove_styles_if_webkit: false,
        paste_webkit_styles: 'all',
        paste_retain_style_properties: 'all',
        paste_merge_formats: false,
        images_upload_url: '{{ route("admin.editor.upload-image") }}',
        images_upload_credentials: true,
        images_upload_handler: function (blobInfo, success, failure) {
            var xhr, formData;
            xhr = new XMLHttpRequest();
            xhr.withCredentials = true;
            xhr.open('POST', '{{ route("admin.editor.upload-image") }}');
            xhr.setRequestHeader('X-CSRF-TOKEN', '{{ csrf_token() }}');
            xhr.onload = function() {
                var json;
                if (xhr.status != 200) {
                    failure('HTTP Error: ' + xhr.status);
                    return;
                }
                json = JSON.parse(xhr.responseText);
                if (!json || typeof json.location != 'string') {
                    failure('Invalid JSON: ' + xhr.responseText);
                    return;
                }
                success(json.location);
            };
            xhr.onerror = function () {
                failure('Image upload failed due to a network error');
            };
            formData = new FormData();
            formData.append('file', blobInfo.blob(), blobInfo.filename());
            formData.append('_token', '{{ csrf_token() }}');
            xhr.send(formData);
        },
        
        setup: function (editor) {
            editor.on('init change', function () {
                editor.save();
            });
        },

        directionality : 'ltr',
        language: '{{ $version->code }}',
      });
    </script>
    @endif

    <!-- Real-time Clock Script -->
    <script type="text/javascript">
        "use strict";
        function updateClock() {
            const now = new Date();
            
            // Format date: Monday, October 21, 2025
            const dateOptions = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            const dateString = now.toLocaleDateString('en-US', dateOptions);
            
            // Format time: 12:45:30 PM
            const hours = now.getHours();
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            const ampm = hours >= 12 ? 'PM' : 'AM';
            const displayHours = hours % 12 || 12;
            const timeString = displayHours + ':' + minutes + ':' + seconds + ' ' + ampm;
            
            // Update DOM elements
            const dateElement = document.getElementById('current-date');
            const timeElement = document.getElementById('current-time');
            
            if (dateElement && timeElement) {
                dateElement.textContent = dateString;
                timeElement.textContent = timeString;
            }
        }
        
        // Update immediately on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateClock();
            // Update every second
            setInterval(updateClock, 1000);
        });
    </script>

    <!-- Session Timeout & Connection Monitor Script -->
    <script type="text/javascript">
        "use strict";
        $(document).ready(function() {
            // Session configuration
            const SESSION_LIFETIME = {{ config('session.lifetime') }} * 60 * 1000; // Convert minutes to milliseconds
            const WARNING_TIME = 5 * 60 * 1000; // Show warning 5 minutes before timeout
            const CHECK_INTERVAL = 60 * 1000; // Check every minute
            
            let lastActivity = Date.now();
            let warningShown = false;
            let sessionCheckInterval;
            
            // Update last activity time on user interactions
            function updateActivity() {
                lastActivity = Date.now();
                warningShown = false;
                var $m = $('#session-timeout-modal');
                if ($m.length && typeof $m.modal === 'function') { $m.modal('hide'); }
            }
            
            // Monitor user activity
            $(document).on('mousemove keypress click scroll', updateActivity);
            
            // Check session status
            function checkSession() {
                const currentTime = Date.now();
                const inactiveTime = currentTime - lastActivity;
                const timeUntilTimeout = SESSION_LIFETIME - inactiveTime;
                
                // Show warning if approaching timeout
                if (timeUntilTimeout <= WARNING_TIME && timeUntilTimeout > 0 && !warningShown) {
                    warningShown = true;
                    const minutesLeft = Math.ceil(timeUntilTimeout / 60000);
                    $('#session-minutes-left').text(minutesLeft);
                    var $m2 = $('#session-timeout-modal');
                    if ($m2.length && typeof $m2.modal === 'function') { $m2.modal('show'); }
                }
                
                // Session expired - redirect to login
                if (timeUntilTimeout <= 0) {
                    clearInterval(sessionCheckInterval);
                    window.location.href = "{{ route('login') }}?session_expired=1";
                }
            }
            
            // Start session monitoring
            sessionCheckInterval = setInterval(checkSession, CHECK_INTERVAL);
            
            // Extend session button
            $('#extend-session-btn').on('click', function() {
                $.ajax({
                    url: "{{ route('admin.dashboard.index') }}", // Ping any authenticated route
                    method: 'GET',
                    success: function() {
                        updateActivity();
                        toastr.success('Session extended successfully!');
                    },
                    error: function() {
                        toastr.error('Failed to extend session. Please login again.');
                        window.location.href = "{{ route('login') }}";
                    }
                });
            });
            
            // Internet connection monitoring
            let isOnline = navigator.onLine;
            const connectionStatus = $('#connection-status');
            const connectionIcon = $('#connection-icon');
            
            function updateConnectionStatus(online) {
                isOnline = online;
                if (online) {
                    connectionStatus.removeClass('offline').addClass('online');
                    connectionIcon.removeClass('fa-wifi-slash').addClass('fa-wifi');
                    connectionStatus.attr('title', 'Internet Connected');
                } else {
                    connectionStatus.removeClass('online').addClass('offline');
                    connectionIcon.removeClass('fa-wifi').addClass('fa-wifi-slash');
                    connectionStatus.attr('title', 'No Internet Connection');
                    toastr.warning('Internet connection lost! Working in offline mode.', '', {
                        timeOut: 5000,
                        closeButton: true,
                        progressBar: true
                    });
                }
            }
            
            // Initial status
            updateConnectionStatus(isOnline);
            
            // Listen for online/offline events
            window.addEventListener('online', function() {
                updateConnectionStatus(true);
                toastr.success('Internet connection restored!', '', {
                    timeOut: 3000,
                    closeButton: true
                });
            });
            
            window.addEventListener('offline', function() {
                updateConnectionStatus(false);
            });
            
            // Periodic connection check (every 30 seconds)
            setInterval(function() {
                fetch('{{ asset("dashboard/css/style.css") }}', { 
                    method: 'HEAD',
                    cache: 'no-cache'
                })
                .then(function() {
                    if (!isOnline) {
                        updateConnectionStatus(true);
                        toastr.success('Internet connection restored!');
                    }
                })
                .catch(function() {
                    if (isOnline) {
                        updateConnectionStatus(false);
                    }
                });
            }, 30000);
        });
    </script>

