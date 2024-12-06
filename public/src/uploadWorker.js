// uploadWorker.js
self.onmessage = function(event) {
    const { file, name, csrfToken } = event.data;
    const formData = new FormData();
    formData.append('file', file);
    formData.append('name', name);

    const xhr = new XMLHttpRequest();
    let lastProgress = 0;
    let isUploading = true;
    let lastUpdateTime = 0;

    function updateProgress(currentTime) {
        if (!isUploading) return;

        // Chỉ cập nhật mỗi 1000ms (1 giây)
        if (currentTime - lastUpdateTime >= 1000) {
            self.postMessage('Upload progress: ' + lastProgress + '%');
            lastUpdateTime = currentTime;
        }

        requestAnimationFrame(updateProgress);
    }

    xhr.open('POST', '/upload', true);
    xhr.setRequestHeader('X-CSRF-Token', csrfToken);

    xhr.upload.onprogress = function(event) {
        if (event.lengthComputable) {
            const percentComplete = (event.loaded / event.total) * 100;
            lastProgress = Math.floor(percentComplete);
        }
    };

    // Bắt đầu vòng lặp cập nhật
    requestAnimationFrame(updateProgress);

    xhr.onload = function() {
        isUploading = false;
        if (xhr.status >= 200 && xhr.status < 300) {
            self.postMessage('Upload successful: ' + xhr.responseText);
        } else {
            // Xử lý lỗi xác thực
            const response = JSON.parse(xhr.responseText);
            if (response.errors) {
                self.postMessage('Error: ' + JSON.stringify(response.errors));
            } else {
                self.postMessage('Error: Upload failed with status ' + xhr.status);
            }
        }
    };

    xhr.onerror = function() {
        isUploading = false;
        self.postMessage('Error: Upload failed');
    };

    xhr.send(formData);
};
