<form action="{{ route('student.platform-fee.upload') }}" method="POST" enctype="multipart/form-data" id="paymentForm">
    @csrf
    
    <div class="upload-area" id="uploadArea">
        <i class="fas fa-cloud-upload-alt"></i>
        <h4>Click or Drag to Upload Receipt</h4>
        <p id="fileName" style="margin-top: 10px; color: #718096;">No file chosen</p>
        <small style="color: #a0aec0;">Supported formats: JPG, PNG, PDF (Max 2MB)</small>
        <input type="file" name="receipt" id="receipt" accept=".jpg,.jpeg,.png,.pdf" style="display: none;" required>
    </div>

    @error('receipt')
        <div class="alert alert-danger mt-2">{{ $message }}</div>
    @enderror

    <div class="row mt-4">
        <div class="col-md-6">
            <div class="form-group">
                <label for="payment_date"><i class="fas fa-calendar"></i> Payment Date <span class="text-danger">*</span></label>
                <input type="date" class="form-control" name="payment_date" id="payment_date" value="{{ old('payment_date', date('Y-m-d')) }}" required style="border-radius: 10px; padding: 12px;">
                @error('payment_date')
                    <small class="text-danger">{{ $message }}</small>
                @enderror
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label for="student_note"><i class="fas fa-sticky-note"></i> Additional Note (Optional)</label>
                <textarea class="form-control" name="student_note" id="student_note" rows="3" placeholder="Any additional information..." style="border-radius: 10px; padding: 12px;">{{ old('student_note') }}</textarea>
                @error('student_note')
                    <small class="text-danger">{{ $message }}</small>
                @enderror
            </div>
        </div>
    </div>

    <div class="text-center mt-4">
        <button type="submit" class="btn btn-primary-custom">
            <i class="fas fa-upload"></i> Submit Payment Receipt
        </button>
    </div>
</form>
