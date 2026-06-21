@extends('admin.layouts.master')
@section('title', $title)

@section('page_css')
<!-- Google Fonts - Anton & Oswald for ID Card -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Anton&family=Oswald:wght@300;400;500;600&display=swap" rel="stylesheet">
@endsection

@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ $title }} {{ __('list') }}</h5>
                    </div>
                    <div class="card-block">
                        <form class="needs-validation" novalidate method="get" action="{{ route($route.'.index') }}">
                            <div class="row gx-2">
                                <div class="form-group col-md-3">
                                    <label for="department">{{ __('field_department') }}</label>
                                    <select class="form-control" name="department" id="department">
                                        <option value="0">{{ __('all') }}</option>
                                        @foreach( $departments as $department )
                                        <option value="{{ $department->id }}" @if( $selected_department == $department->id) selected @endif>{{ $department->title }}</option>
                                        @endforeach
                                    </select>

                                    <div class="invalid-feedback">
                                      {{ __('required_field') }} {{ __('field_department') }}
                                    </div>
                                </div>

                                <div class="form-group col-md-3">
                                    <label for="designation">{{ __('field_designation') }}</label>
                                    <select class="form-control" name="designation" id="designation">
                                        <option value="0">{{ __('all') }}</option>
                                        @foreach( $designations as $designation )
                                        <option value="{{ $designation->id }}" @if( $selected_designation == $designation->id) selected @endif>{{ $designation->title }}</option>
                                        @endforeach
                                    </select>

                                    <div class="invalid-feedback">
                                      {{ __('required_field') }} {{ __('field_designation') }}
                                    </div>
                                </div>

                                <div class="form-group col-md-3">
                                    <label for="staff_id">{{ __('field_staff_id') }}</label>
                                    <input type="text" class="form-control" name="staff_id" id="staff_id" value="{{ $selected_staff_id }}">

                                    <div class="invalid-feedback">
                                      {{ __('required_field') }} {{ __('field_staff_id') }}
                                    </div>
                                </div>
                                
                                <div class="form-group col-md-3">
                                    <button type="submit" class="btn btn-info btn-filter"><i class="fas fa-search"></i> {{ __('btn_search') }}</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            @isset($rows)
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        @if(isset($print))
                        <form class="needs-validation d-inline" novalidate method="get" action="{{ route($route.'.multiprint') }}" target="_blank">
                            <input type="hidden" name="staffs" class="staffs" value="">
                            <button type="submit" class="btn btn-sm btn-dark print-btn"><i class="fas fa-print"></i> {{ __('btn_print') }} {{ __('field_selected') }}</button>
                        </form>
                        
                        <!-- Download as ZIP button -->
                        <button type="button" class="btn btn-sm btn-success download-zip-btn" onclick="downloadSelectedAsZip()">
                            <i class="fas fa-file-archive"></i> Download ZIP
                        </button>
                        @endif
                    </div>
                    <div class="card-block">
                        <!-- [ Data table ] start -->
                        <div class="table-responsive">
                            <table id="basic-table" class="display table nowrap table-striped table-hover" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>
                                            <div class="checkbox checkbox-success d-inline">
                                                <input type="checkbox" id="checkbox" class="all_select">
                                                <label for="checkbox" class="cr" style="margin-bottom: 0px;"></label>
                                            </div>
                                        </th>
                                        <th>{{ __('field_photo') }}</th>
                                        <th>{{ __('field_staff_id') }}</th>
                                        <th>{{ __('field_name') }}</th>
                                        <th>{{ __('field_department') }}</th>
                                        <th>{{ __('field_designation') }}</th>
                                        <th>{{ __('field_gender') }}</th>
                                        <th>{{ __('field_joining_date') }}</th>
                                        <th>{{ __('field_action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                  @foreach( $rows as $key => $row )
                                    <tr>
                                        <td>
                                            <div class="checkbox checkbox-primary d-inline">
                                                <input type="checkbox" data_id="{{ $row->id }}" id="checkbox-{{ $row->id }}" value="{{ $row->id }}">
                                                <label for="checkbox-{{ $row->id }}" class="cr"></label>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="photo-container" style="position: relative; display: inline-block; cursor: pointer;" 
                                                 onclick="openPhotoModal({{ $row->id }}, '{{ $row->first_name }} {{ $row->last_name }}', '{{ $row->photo ? asset('uploads/user/'.$row->photo) : asset('dashboard/images/user.jpg') }}')">
                                                <img id="photo-preview-{{ $row->id }}" 
                                                     src="{{ $row->photo && file_exists(public_path('uploads/user/'.$row->photo)) ? asset('uploads/user/'.$row->photo) : asset('dashboard/images/user.jpg') }}" 
                                                     alt="{{ $row->first_name }}" 
                                                     class="img-fluid rounded-circle"
                                                     style="width: 45px; height: 45px; object-fit: cover; border: 2px solid {{ $row->photo && file_exists(public_path('uploads/user/'.$row->photo)) ? '#28a745' : '#dc3545' }};">
                                                <span class="photo-edit-icon" style="position: absolute; bottom: -2px; right: -2px; background: #667eea; color: white; border-radius: 50%; width: 18px; height: 18px; display: flex; align-items: center; justify-content: center; font-size: 10px;">
                                                    <i class="fas fa-camera"></i>
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <a href="{{ route('admin.user.show', $row->id) }}">
                                            #{{ $row->staff_id }}
                                            </a>
                                        </td>
                                        <td>{{ $row->first_name }} {{ $row->last_name }}</td>
                                        <td>{{ $row->department->title ?? '-' }}</td>
                                        <td>{{ $row->designation->title ?? '-' }}</td>
                                        <td>
                                            @if( $row->gender == 1 )
                                            {{ __('gender_male') }}
                                            @elseif( $row->gender == 2 )
                                            {{ __('gender_female') }}
                                            @elseif( $row->gender == 3 )
                                            {{ __('gender_other') }}
                                            @endif
                                        </td>
                                        <td>
                                            @if(isset($row->joining_date))
                                            {{ date('d M Y', strtotime($row->joining_date)) }}
                                            @else
                                            -
                                            @endif
                                        </td>
                                        <td>
                                            @if(isset($print))
                                            <a href="#" class="btn btn-icon btn-dark btn-sm" onclick="PopupWin('{{ route($route.'.print', ['id' => $row->id]) }}', '{{ $title }}', 1000, 600);" title="{{ __('btn_print') }}">
                                                <i class="fas fa-print"></i>
                                            </a>
                                            <a href="{{ route($route.'.download', ['id' => $row->id]) }}" class="btn btn-icon btn-success btn-sm" target="_blank" title="{{ __('btn_download') }}">
                                                <i class="fas fa-download"></i>
                                            </a>
                                            @endif
                                        </td>
                                    </tr>
                                  @endforeach
                                </tbody>
                            </table>
                        </div>
                        <!-- [ Data table ] end -->
                    </div>
                </div>
            </div>
            @endisset
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

<!-- Photo Update Modal -->
<div class="modal fade" id="photoUpdateModal" tabindex="-1" role="dialog" aria-labelledby="photoUpdateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="photoUpdateModalLabel">
                    <i class="fas fa-camera"></i> {{ __('field_photo') }} - <span id="modal-staff-name"></span>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" onclick="$('#photoUpdateModal').modal('hide');">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="photoUpdateForm" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" id="modal-staff-id" name="staff_id" value="">
                    
                    <div class="text-center mb-4">
                        <div style="position: relative; display: inline-block;">
                            <img id="modal-photo-preview" src="" alt="Staff Photo" 
                                 class="img-fluid rounded-circle" 
                                 style="width: 150px; height: 150px; object-fit: cover; border: 4px solid #667eea;">
                            <div id="photo-loading" style="display: none; position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255,255,255,0.8); border-radius: 50%; align-items: center; justify-content: center;">
                                <i class="fas fa-spinner fa-spin fa-2x text-primary"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="photo-input" class="form-label">
                            {{ __('field_photo') }}: <span class="text-muted">{{ __('image_size', ['height' => 300, 'width' => 300]) }}</span>
                        </label>
                        <div class="custom-file">
                            <input type="file" class="custom-file-input" id="photo-input" name="photo" accept="image/jpeg,image/png,image/gif,image/webp">
                            <label class="custom-file-label" for="photo-input" id="photo-input-label">{{ __('choose_file') }}</label>
                        </div>
                        <small class="form-text text-muted">
                            <i class="fas fa-info-circle"></i> {{ __('Supported formats') }}: JPG, JPEG, PNG, GIF, WebP. {{ __('Max size') }}: 3MB
                        </small>
                    </div>
                    
                    <div id="photo-error" class="alert alert-danger" style="display: none;"></div>
                    <div id="photo-success" class="alert alert-success" style="display: none;"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal" onclick="$('#photoUpdateModal').modal('hide');">
                    <i class="fas fa-times"></i> {{ __('btn_close') }}
                </button>
                <button type="button" class="btn btn-primary" id="btn-upload-photo" onclick="uploadPhoto()">
                    <i class="fas fa-upload"></i> {{ __('btn_save') }}
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('page_js')
<!-- JSZip and FileSaver for ZIP download -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<script type="text/javascript">
    "use strict";
    
    // Current staff being edited
    var currentStaffId = null;
    
    // Open photo update modal
    function openPhotoModal(staffId, staffName, currentPhotoUrl) {
        currentStaffId = staffId;
        $('#modal-staff-id').val(staffId);
        $('#modal-staff-name').text(staffName);
        $('#modal-photo-preview').attr('src', currentPhotoUrl);
        $('#photo-input').val('');
        $('#photo-input-label').text('{{ __("choose_file") }}');
        $('#photo-error').hide();
        $('#photo-success').hide();
        $('#photoUpdateModal').modal('show');
    }
    
    // Preview selected file
    $('#photo-input').on('change', function() {
        var fileName = $(this).val().split('\\').pop();
        $('#photo-input-label').text(fileName || '{{ __("choose_file") }}');
        
        // Preview the selected image
        if (this.files && this.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                $('#modal-photo-preview').attr('src', e.target.result);
            }
            reader.readAsDataURL(this.files[0]);
        }
    });
    
    // Upload photo via AJAX
    function uploadPhoto() {
        var fileInput = document.getElementById('photo-input');
        if (!fileInput.files.length) {
            $('#photo-error').text('{{ __("Please select a photo") }}').show();
            return;
        }
        
        var formData = new FormData();
        formData.append('photo', fileInput.files[0]);
        formData.append('_token', '{{ csrf_token() }}');
        
        // Show loading
        $('#btn-upload-photo').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> {{ __("Uploading") }}...');
        $('#photo-loading').css('display', 'flex');
        $('#photo-error').hide();
        $('#photo-success').hide();
        
        $.ajax({
            url: '{{ url("admin/staff/staff-id-card-update-photo") }}/' + currentStaffId,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    // Update modal preview
                    $('#modal-photo-preview').attr('src', response.photo_url);
                    
                    // Update table preview with cache buster
                    var newUrl = response.photo_url + '?t=' + new Date().getTime();
                    $('#photo-preview-' + currentStaffId)
                        .attr('src', newUrl)
                        .css('border-color', '#28a745');
                    
                    $('#photo-success').text(response.message).show();
                    
                    // Close modal after 1.5 seconds
                    setTimeout(function() {
                        $('#photoUpdateModal').modal('hide');
                    }, 1500);
                } else {
                    $('#photo-error').text(response.message).show();
                }
            },
            error: function(xhr) {
                var message = '{{ __("Upload failed") }}';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }
                $('#photo-error').text(message).show();
            },
            complete: function() {
                $('#btn-upload-photo').prop('disabled', false).html('<i class="fas fa-upload"></i> {{ __("btn_save") }}');
                $('#photo-loading').hide();
            }
        });
    }
    
    // Download selected ID cards as ZIP
    function downloadSelectedAsZip() {
        var numberOfChecked = $("input[data_id]:checked").length;
        if (numberOfChecked <= 0) {
            alert("{{ __('select') }} {{ __('field_staff_id') }}");
            return;
        }
        
        var staffs = [];
        $.each($("input[data_id]:checked"), function() {
            staffs.push($(this).val());
        });
        
        // Show loading modal
        showZipLoadingModal(numberOfChecked);
        
        // Get staff data from server
        $.ajax({
            url: '{{ route("admin.staff-id-card.download-zip") }}',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                staffs: staffs.join(',')
            },
            success: function(response) {
                if (response.success) {
                    generateZipFile(response.staffs, response.template_url);
                } else {
                    hideZipLoadingModal();
                    alert(response.message || 'Error fetching staff data');
                }
            },
            error: function(xhr) {
                hideZipLoadingModal();
                alert('Error fetching staff data. Please try again.');
            }
        });
    }
    
    // Show ZIP loading modal
    function showZipLoadingModal(count) {
        var modalHtml = `
            <div id="zipLoadingModal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 9999; display: flex; align-items: center; justify-content: center;">
                <div style="background: white; padding: 30px 50px; border-radius: 10px; text-align: center;">
                    <div style="width: 50px; height: 50px; border: 5px solid #f3f3f3; border-top: 5px solid #667eea; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 15px;"></div>
                    <h4 style="margin: 0 0 10px;">Generating ID Cards...</h4>
                    <p id="zipProgress" style="margin: 0; color: #666;">Processing 0 of ${count} cards</p>
                </div>
            </div>
            <style>
                @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
            </style>
        `;
        $('body').append(modalHtml);
    }
    
    // Hide ZIP loading modal
    function hideZipLoadingModal() {
        $('#zipLoadingModal').remove();
    }
    
    // Update ZIP progress
    function updateZipProgress(current, total, status) {
        if (status) {
            $('#zipProgress').text(status);
        } else {
            $('#zipProgress').text('Processing ' + current + ' of ' + total + ' cards');
        }
    }
    
    // Convert image URL to base64
    async function imageToBase64(url) {
        return new Promise((resolve, reject) => {
            const img = new Image();
            img.crossOrigin = 'anonymous';
            img.onload = function() {
                const canvas = document.createElement('canvas');
                canvas.width = img.naturalWidth;
                canvas.height = img.naturalHeight;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0);
                resolve(canvas.toDataURL('image/png'));
            };
            img.onerror = function() {
                // Return a placeholder if image fails
                resolve('data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
            };
            img.src = url;
        });
    }

    // Generate ZIP file with ID card images
    async function generateZipFile(staffs, templateUrl) {
        const zip = new JSZip();
        const folder = zip.folder("Staff_ID_Cards");
        let processed = 0;
        const total = staffs.length;
        
        // Pre-load template as base64
        updateZipProgress(0, total, 'Loading template...');
        const templateBase64 = await imageToBase64(templateUrl);
        
        // Create a hidden container for rendering ID cards
        const container = document.createElement('div');
        container.id = 'zip-render-container';
        container.style.cssText = 'position: absolute; left: -9999px; top: 0;';
        document.body.appendChild(container);
        
        // Process each staff member
        for (const staff of staffs) {
            try {
                updateZipProgress(processed, total, 'Loading images for ' + staff.staff_id + '...');
                
                // Convert staff photo to base64
                const photoBase64 = await imageToBase64(staff.photo);
                
                // Convert QR code to base64
                const qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' + encodeURIComponent(staff.qr_url);
                const qrBase64 = await imageToBase64(qrUrl);
                
                // Create ID card HTML with base64 images
                const cardHtml = createStaffIdCardHtml(staff, templateBase64, photoBase64, qrBase64);
                container.innerHTML = cardHtml;
                
                // Wait for images to load (should be instant with base64)
                await waitForImages(container);
                
                // Small delay to ensure rendering is complete
                await new Promise(resolve => setTimeout(resolve, 100));
                
                // Capture as canvas
                const card = container.querySelector('.zip-id-card');
                const canvas = await html2canvas(card, {
                    scale: 2,
                    useCORS: true,
                    allowTaint: true,
                    backgroundColor: null,
                    logging: false
                });
                
                // Add to ZIP
                const imgData = canvas.toDataURL('image/png').split(',')[1];
                folder.file('Staff_ID_' + staff.staff_id + '.png', imgData, {base64: true});
                
                processed++;
                updateZipProgress(processed, total);
            } catch (error) {
                console.error('Error processing staff:', staff.staff_id, error);
                processed++;
                updateZipProgress(processed, total);
            }
        }
        
        // Remove hidden container
        document.body.removeChild(container);
        
        // Generate and download ZIP
        zip.generateAsync({type: 'blob'}).then(function(content) {
            saveAs(content, 'Staff_ID_Cards_' + new Date().toISOString().slice(0,10) + '.zip');
            hideZipLoadingModal();
        });
    }
    
    // Create Staff ID card HTML for ZIP generation (using base64 images)
    function createStaffIdCardHtml(staff, templateBase64, photoBase64, qrBase64) {
        const anton = "font-family: 'Anton', sans-serif";
        const oswald = "font-family: 'Oswald', sans-serif";

        // Photo: top 325, 330x330, centered. Card width 639 -> photoLeft 154.5, right edge 484.5, bottom 655
        // QR (110x110) centered on photo's bottom-right corner -> top 555, left 430 (matches print/download views)
        const photoLeft = (639 - 330) / 2;

        return `
            <div class="zip-id-card" style="width: 639px; height: 1011px; position: relative; background-image: url('${templateBase64}'); background-repeat: no-repeat; background-position: center center; background-size: 639px 1011px; ${anton};">
                <div style="position: absolute; top: 325px; left: ${photoLeft}px; width: 330px; height: 330px; border-radius: 70px; overflow: hidden; background: #fff;">
                    <img src="${photoBase64}" style="width: 330px; height: 330px; object-fit: cover; display: block;">
                </div>
                <div style="position: absolute; top: 555px; left: 430px; width: 110px; height: 110px; background: white; padding: 5px; border-radius: 10px; box-sizing: border-box; box-shadow: 0 2px 6px rgba(0,0,0,0.25);">
                    <img src="${qrBase64}" style="width: 100%; height: 100%; display: block;">
                </div>
                <div style="position: absolute; top: 685px; left: 0; width: 639px; text-align: center; ${anton}; font-size: 50px; font-weight: 400; letter-spacing: 4px; color: #000; text-transform: uppercase;">${staff.first_name}</div>
                <div style="position: absolute; top: 740px; left: 0; width: 639px; text-align: center; ${oswald}; font-size: 44px; font-weight: 500; letter-spacing: 3px; color: #000; text-transform: uppercase;">${staff.last_name}</div>
                <div style="position: absolute; top: 815px; left: 0; width: 639px; text-align: center; ${oswald}; font-size: 28px; font-weight: 500; letter-spacing: 2px; color: #000; text-transform: uppercase;">${staff.designation}</div>
                <div style="position: absolute; top: 885px; left: 0; width: 639px; text-align: center; ${oswald}; font-size: 44px; font-weight: 500; letter-spacing: 2px; color: #000;">ID:${staff.staff_id}</div>
                <div style="position: absolute; top: 938px; left: 0; width: 639px; text-align: center; ${oswald}; font-size: 28px; font-weight: 300; color: #000;">VALIDITY: ${staff.validity}</div>
            </div>
        `;
    }
    
    // Wait for all images in container to load
    function waitForImages(container) {
        const images = container.querySelectorAll('img');
        const promises = Array.from(images).map(img => {
            if (img.complete) return Promise.resolve();
            return new Promise((resolve, reject) => {
                img.onload = resolve;
                img.onerror = resolve; // Continue even if image fails
            });
        });
        return Promise.all(promises);
    }

    $(".all_select").on('click',function(){
        $('.print-btn').prop('disabled', false);

        if(this.checked){
            $('.checkbox-primary input').each(function(){
                this.checked = true;
            });
        }
        else{
            $('.checkbox-primary input').each(function(){
                this.checked = false;
            });
        }
    });

    $('.checkbox-primary input').on('click',function(){
        $('.print-btn').prop('disabled', false);
    });

    $('.print-btn').on('click',function(e){
        var allVals = [];
        $('.checkbox-primary input:checkbox:checked').each(function() {
            allVals.push($(this).val());
        });

        if(allVals.length <= 0)
        {
            $('.print-btn').prop('disabled', true);
            alert('{{ __("select_atleast_one") }}');
            e.preventDefault();
        }
        else{
            $('.staffs').val(allVals);
        }
    });
</script>
@endsection
