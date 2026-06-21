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

    <!-- material datetimepicker Js -->
    <script src="{{ asset('dashboard/plugins/moment/js/moment-with-locales.min.js') }}"></script>
    <script src="{{ asset('dashboard/plugins/material-datetimepicker/js/bootstrap-material-datetimepicker.js') }}"></script>

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
            // Date Picker
            $('.date').bootstrapMaterialDatePicker({
                setDate: new Date(),
                weekStart: 0,
                time: false
            });

            // Time Picker
            $('.time').bootstrapMaterialDatePicker({
                date: false,
                format: 'HH:mm'
            });
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
                        exportOptions: {
                            columns: ':not(:last-child)',
                        }
                    },
                    {
                        extend: 'excelHtml5',
                        text: '<i class="fas fa-file-excel"></i>',
                        exportOptions: {
                            columns: ':not(:last-child)',
                        }
                    },
                    {
                        extend: 'csvHtml5',
                        text: '<i class="fas fa-file"></i>',
                        exportOptions: {
                            columns: ':not(:last-child)',
                        }
                    },
                    {
                        extend: 'pdfHtml5',
                        text: '<i class="fas fa-file-pdf"></i>',
                        exportOptions: {
                            columns: ':not(:last-child)',
                        }
                    },
                    {
                        extend: 'print',
                        text: '<i class="fas fa-print"></i>',
                        autoPrint: true,
                        // title: '',
                        footer: false,
                        exportOptions: {
                            columns: ':not(:last-child)',
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

            // Close sidebar when clicking anywhere outside it
            $(document).on('click touchstart', function(e) {
                if (!$navbar.hasClass('mob-open')) return;
                var $target = $(e.target);
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
                $navbar.removeClass('navbar-collapsed');
                $('body').removeClass('sidebar-open');
            }
        });
    </script>

    {{-- Page specific scripts --}}
    @stack('scripts')