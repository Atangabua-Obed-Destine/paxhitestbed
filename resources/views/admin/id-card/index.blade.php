@extends('admin.layouts.master')
@section('title', $title)
@section('content')
{{-- The card artwork is uploaded under the ID Card Setting screen. Until one
     is uploaded we fall back to the file that used to be hardcoded here, so an
     install that has not configured anything looks exactly as it did before. --}}
@php
    $cardBackground = !empty($print->background)
        ? asset('uploads/card-setting/' . $print->background)
        : asset('uploads/templates/paxid.jpg');
@endphp

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
                                @include('common.inc.student_search_filter')

                                <div class="form-group col-md-3">
                                    <label for="student_id">{{ __('field_matricule') }} / {{ __('field_student_id') }}</label>
                                    <input type="text" class="form-control" name="student_id" id="student_id" value="{{ $selected_student_id }}">

                                    <div class="invalid-feedback">
                                      {{ __('required_field') }} {{ __('field_matricule') }}
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
                        
                        @can($access.'-card')
                        <form class="needs-validation d-inline" novalidate method="get" action="{{ route($route.'.multiprint') }}" target="_blank">
                            <input type="hidden" name="students" class="students" value="">
                            <button type="submit" class="btn btn-sm btn-dark print-btn"><i class="fas fa-print"></i> {{ __('btn_print') }} {{ __('field_selected') }}</button>
                        </form>
                        
                        <!-- Download as ZIP button -->
                        <button type="button" class="btn btn-sm btn-success download-zip-btn" onclick="downloadSelectedAsZip()">
                            <i class="fas fa-file-archive"></i> Download ZIP
                        </button>
                        @endcan
                        
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
                                        <th>{{ __('field_matricule') }}</th>
                                        <th>{{ __('field_name') }}</th>
                                        <th>{{ __('field_program') }}</th>
                                        <th>{{ __('field_session') }}</th>
                                        <th>{{ __('field_semester') }}</th>
                                        <th>{{ __('field_section') }}</th>
                                        <th>{{ __('field_action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                  @foreach( $rows as $key => $row )
                                  @php
                                    // $row is now an enrollment, not a student
                                    $student = $row->student;
                                  @endphp
                                    <tr>
                                        <td>
                                            <div class="checkbox checkbox-primary d-inline">
                                                <input type="checkbox" data_id="{{ $row->id }}" id="checkbox-{{ $row->id }}" value="{{ $row->id }}">
                                                <label for="checkbox-{{ $row->id }}" class="cr"></label>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="photo-container" style="position: relative; display: inline-block; cursor: pointer;" 
                                                 onclick="openPhotoModal({{ $student->id }}, '{{ $student->first_name }} {{ $student->last_name }}', '{{ $student->photo ? asset('uploads/student/'.$student->photo) : asset('dashboard/images/user.jpg') }}')">
                                                <img id="photo-preview-{{ $student->id }}" 
                                                     src="{{ $student->photo && file_exists(public_path('uploads/student/'.$student->photo)) ? asset('uploads/student/'.$student->photo) : asset('dashboard/images/user.jpg') }}" 
                                                     alt="{{ $student->first_name }}" 
                                                     class="img-fluid rounded-circle"
                                                     style="width: 45px; height: 45px; object-fit: cover; border: 2px solid {{ $student->photo && file_exists(public_path('uploads/student/'.$student->photo)) ? '#28a745' : '#dc3545' }};">
                                                <span class="photo-edit-icon" style="position: absolute; bottom: -2px; right: -2px; background: #667eea; color: white; border-radius: 50%; width: 18px; height: 18px; display: flex; align-items: center; justify-content: center; font-size: 10px;">
                                                    <i class="fas fa-camera"></i>
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <a href="{{ route('admin.student.show', $student->id) }}">
                                            <strong style="color: #667eea;">#{{ $row->matricule ?? $student->student_id }}</strong>
                                            @if($row->program)
                                                <br>
                                                <span class="badge" style="background: {{ $row->program->academic_level == 'M' ? '#f5576c' : ($row->program->academic_level == 'D' ? '#00f2fe' : '#38f9d7') }}; color: white; font-size: 9px;">
                                                    {{ $row->program->academic_level == 'A' ? 'UG' : ($row->program->academic_level == 'M' ? 'MS' : 'PhD') }}
                                                </span>
                                            @endif
                                            </a>
                                        </td>
                                        <td>{{ $student->first_name }} {{ $student->last_name }}</td>
                                        <td>{{ $row->program->shortcode ?? '' }}</td>
                                        <td>{{ $row->session->title ?? '' }}</td>
                                        <td>{{ $row->semester->title ?? '' }}</td>
                                        <td>{{ $row->section->title ?? '' }}</td>
                                        <td>
                                            @can($access.'-card')
                                            @if(isset($print))
                                            <a href="#" class="btn btn-icon btn-dark btn-sm" onclick="PopupWin('{{ route($route.'.print', ['id' => $row->id]) }}', '{{ $title }}', 1000, 600);" title="{{ __('btn_print') }}">
                                                <i class="fas fa-print"></i>
                                            </a>
                                            <a href="{{ route($route.'.download', ['id' => $row->id]) }}" class="btn btn-icon btn-success btn-sm" target="_blank" title="{{ __('btn_download') }}">
                                                <i class="fas fa-download"></i>
                                            </a>
                                            @endif
                                            @endcan
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
                    <i class="fas fa-camera"></i> {{ __('field_photo') }} - <span id="modal-student-name"></span>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" onclick="$('#photoUpdateModal').modal('hide');">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="photoUpdateForm" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" id="modal-student-id" name="student_id" value="">
                    
                    <div class="text-center mb-4">
                        <div style="position: relative; display: inline-block;">
                            <img id="modal-photo-preview" src="" alt="Student Photo" 
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
    
    // Current student being edited
    var currentStudentId = null;
    
    // Open photo update modal
    function openPhotoModal(studentId, studentName, currentPhotoUrl) {
        currentStudentId = studentId;
        $('#modal-student-id').val(studentId);
        $('#modal-student-name').text(studentName);
        $('#modal-photo-preview').attr('src', currentPhotoUrl);
        $('#photo-input').val('');
        $('#photo-input-label').text('{{ __("choose_file") }}');
        $('#photo-error').hide();
        $('#photo-success').hide();
        $('#photoUpdateModal').modal('show');
    }
    
    // Download selected ID cards as ZIP
    function downloadSelectedAsZip() {
        var numberOfChecked = $("input[data_id]:checked").length;
        if (numberOfChecked <= 0) {
            alert("{{ __('select') }} {{ __('field_student_id') }}");
            return;
        }
        
        var students = [];
        $.each($("input[data_id]:checked"), function() {
            students.push($(this).val());
        });
        
        // Show loading modal
        showZipLoadingModal(numberOfChecked);
        
        // Get student data from server
        $.ajax({
            url: '{{ route("admin.id-card.download-zip") }}',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                students: students.join(',')
            },
            success: function(response) {
                if (response.success) {
                    generateZipFile(response.students);
                } else {
                    hideZipLoadingModal();
                    alert(response.message || 'Error fetching student data');
                }
            },
            error: function(xhr) {
                hideZipLoadingModal();
                alert('Error fetching student data. Please try again.');
            }
        });
    }
    
    // Generate ZIP file with ID card images
    async function generateZipFile(students) {
        const zip = new JSZip();
        const folder = zip.folder("ID_Cards");
        let processed = 0;
        
        // Create a hidden container for rendering ID cards
        const container = document.createElement('div');
        container.id = 'zip-render-container';
        container.style.cssText = 'position: absolute; left: -9999px; top: 0;';
        document.body.appendChild(container);
        
        // Add styles for rendering
        const style = document.createElement('style');
        style.textContent = `
            .zip-id-card {
                width: 711px;
                height: 450px;
                position: relative;
                background: url('{{ $cardBackground }}') no-repeat center center;
                background-size: cover;
                font-family: 'Anton', sans-serif;
                font-style: normal;
                color: #000;
            }
            .zip-id-card div {
                margin-top: 25px;
            }
            .zip-id-card .validity {
                position: absolute;
                top: -5px;
                right: 15px;
                font-size: 14px;
                font-weight: 700;
                color: #fff;
                padding: 5px 10px;
                font-family: 'Anton', sans-serif;
            }
            .zip-id-card .photo {
                position: absolute;
                bottom: 35px;
                right: 15px;
                width: 182px;
                height: 199px;
                border: 2px solid #001F5B;
                overflow: hidden;
            }
            .zip-id-card .photo img {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }
            .zip-id-card .name {
                position: absolute;
                top: 165px;
                left: 150px;
                font-size: 22px;
                font-weight: 700;
                letter-spacing: 0.5px;
                color: #001F5B;
            }
            .zip-id-card .dob,
            .zip-id-card .sex,
            .zip-id-card .matricule,
            .zip-id-card .faculty,
            .zip-id-card .date-issued {
                position: absolute;
                left: 220px;
                font-size: 20px;
                font-weight: 700;
                letter-spacing: 0.3px;
                color: #000;
                font-family: 'Anton', sans-serif;
            }
            .zip-id-card .dob { top: 188px; left: 150px; }
            .zip-id-card .sex { top: 207px; left: 150px; }
            .zip-id-card .matricule { top: 228px; left: 150px; font-weight: 700; }
            .zip-id-card .faculty { top: 251px; left: 150px; }
            .zip-id-card .date-issued { top: 272px; left: 150px; }
            .zip-id-card .qr-code {
                position: absolute;
                bottom: 35px;
                right: 210px;
                width: 110px;
                height: 110px;
                background: white;
                padding: 5px;
                border: 2px solid #001F5B;
                border-radius: 5px;
            }
            .zip-id-card .qr-code img {
                width: 100%;
                height: 100%;
            }
        `;
        container.appendChild(style);
        
        for (const student of students) {
            updateZipProgress(processed + 1, students.length, student.name);
            
            try {
                // Create ID card HTML
                const verificationUrl = '{{ url("verify-student") }}/' + student.matricule;
                const qrCode = 'https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=' + encodeURIComponent(verificationUrl);
                
                const cardHtml = `
                    <div class="zip-id-card" id="card-${student.id}">
                        <div class="validity">VALIDITY: ${student.validity}</div>
                        <div class="photo"><img src="${student.photo}" crossorigin="anonymous"></div>
                        <div class="name">${student.name}</div>
                        <div class="dob">${student.dob}</div>
                        <div class="sex">${student.gender}</div>
                        <div class="matricule">${student.prefix}${student.matricule}</div>
                        <div class="faculty">${student.faculty}</div>
                        <div class="date-issued">${new Date().toLocaleDateString('en-GB')}</div>
                        <div class="qr-code"><img src="${qrCode}" crossorigin="anonymous"></div>
                    </div>
                `;
                
                container.innerHTML = style.outerHTML + cardHtml;
                
                // Wait for images to load
                await new Promise(resolve => setTimeout(resolve, 500));
                
                const card = document.getElementById('card-' + student.id);
                
                // Generate image
                const canvas = await html2canvas(card, {
                    scale: 2,
                    useCORS: true,
                    allowTaint: true,
                    backgroundColor: null
                });
                
                // Convert to blob and add to ZIP
                const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/png'));
                folder.file(`ID_Card_${student.matricule}.png`, blob);
                
                processed++;
            } catch (error) {
                console.error('Error processing student:', student.matricule, error);
                processed++;
            }
        }
        
        // Remove container
        document.body.removeChild(container);
        
        // Generate and download ZIP
        updateZipProgress(students.length, students.length, 'Creating ZIP file...');
        
        zip.generateAsync({ type: 'blob' }).then(function(content) {
            const timestamp = new Date().toISOString().slice(0, 10);
            saveAs(content, `ID_Cards_${timestamp}.zip`);
            hideZipLoadingModal();
        });
    }
    
    // Show ZIP loading modal
    function showZipLoadingModal(total) {
        if (!document.getElementById('zipLoadingModal')) {
            const modalHtml = `
                <div class="modal fade" id="zipLoadingModal" tabindex="-1" data-backdrop="static" data-keyboard="false">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header bg-success text-white">
                                <h5 class="modal-title"><i class="fas fa-file-archive"></i> Generating ZIP File</h5>
                            </div>
                            <div class="modal-body text-center py-4">
                                <div class="mb-3">
                                    <i class="fas fa-spinner fa-spin fa-3x text-success"></i>
                                </div>
                                <h5 id="zipProgressText">Preparing...</h5>
                                <div class="progress mt-3" style="height: 25px;">
                                    <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" 
                                         id="zipProgressBar" role="progressbar" style="width: 0%;">0%</div>
                                </div>
                                <p class="text-muted mt-2" id="zipCurrentStudent"></p>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            $('body').append(modalHtml);
        }
        $('#zipLoadingModal').modal('show');
    }
    
    // Update ZIP progress
    function updateZipProgress(current, total, studentName) {
        const percent = Math.round((current / total) * 100);
        $('#zipProgressText').text(`Processing ${current} of ${total} ID cards...`);
        $('#zipProgressBar').css('width', percent + '%').text(percent + '%');
        $('#zipCurrentStudent').text(studentName || '');
    }
    
    // Hide ZIP loading modal
    function hideZipLoadingModal() {
        $('#zipLoadingModal').modal('hide');
    }
    
    // Handle file selection
    $(document).on('change', '#photo-input', function() {
        var file = this.files[0];
        if (file) {
            // Validate file type
            var validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!validTypes.includes(file.type)) {
                $('#photo-error').text('{{ __("Invalid file type") }}. {{ __("Supported formats") }}: JPG, JPEG, PNG, GIF, WebP').show();
                $(this).val('');
                return;
            }
            
            // Validate file size (3MB max)
            if (file.size > 3 * 1024 * 1024) {
                $('#photo-error').text('{{ __("File too large") }}. {{ __("Max size") }}: 3MB').show();
                $(this).val('');
                return;
            }
            
            $('#photo-error').hide();
            $('#photo-input-label').text(file.name);
            
            // Preview selected image
            var reader = new FileReader();
            reader.onload = function(e) {
                $('#modal-photo-preview').attr('src', e.target.result);
            };
            reader.readAsDataURL(file);
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
            url: '{{ url("admin/admission/id-card-update-photo") }}/' + currentStudentId,
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
                    $('#photo-preview-' + currentStudentId)
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
    
    $(document).ready(function() {
        $(".print-btn").on('click',function(e){

            var numberOfChecked = $("input[data_id]:checked").length;
            if(numberOfChecked <= 0){
                e.preventDefault();
                alert("{{ __('select') }} {{ __('field_student_id') }}");
            }

            var students = [];
            $.each($("input[data_id]:checked"), function(){
                students.push($(this).val());
            });

            $(".students").val( students.join(',') );
        });
    });

    // checkbox all-check-button selector
    $(".all_select").on('click',function(e){
        if($(this).is(":checked")){
            // check all checkbox
            $("input:checkbox").prop('checked', true);
        }
        else if($(this).is(":not(:checked)")){
            // uncheck all checkbox
            $("input:checkbox").prop('checked', false);
        }
    });
</script>
@endsection