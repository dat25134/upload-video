<!DOCTYPE html>
<html>

<head>
    <title>File Upload</title>
    <script>
        const worker = new Worker("{{ asset('src/uploadWorker.js') }}");
        const csrfToken = "{{ csrf_token() }}";

        worker.onmessage = function(event) {
            const progressElement = document.getElementById('progress');
            if (typeof event.data === 'string' && event.data.startsWith('Error:')) {
                progressElement.innerText = event.data; // Hiển thị lỗi
            } else {
                progressElement.innerText = 'Upload status: ' + event.data;
            }
        };

        function uploadFile() {
            const fileInput = document.getElementById('file');
            const nameInput = document.getElementById('name');
            const file = fileInput.files[0];
            const name = nameInput.value;
            const csrfToken = "{{ csrf_token() }}";

            if (file) {
                worker.postMessage({
                    file,
                    name,
                    csrfToken
                });
            }
        }
    </script>
</head>

<body>
    <form onsubmit="uploadFile(); return false;">
        @csrf
        <input type="file" name="file" id="file">
        <input type="text" name="name" id="name" placeholder="Enter your name">
        @error('name')
            <div class="invalid-feedback">
                {{ $message }}
            </div>
        @enderror
        <button type="submit">Upload</button>
    </form>
    <div id="progress"></div>
</body>

</html>
