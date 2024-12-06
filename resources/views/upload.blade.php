<!DOCTYPE html>
<html>
  <head>
    <title>File Upload</title>
    <link
      href="https://transloadit.edgly.net/releases/uppy/v1.6.0/uppy.min.css"
      rel="stylesheet"
    />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/progressbar.js/1.1.0/progressbar.min.js"></script>
    <style>
      #progress-container {
        position: fixed;
        top: 10px;
        right: 10px;
        width: 100px;
        height: 100px;
        display: none; /* Ẩn ban đầu */
      }
    </style>
  </head>
  <body>
    <div id="uppy"></div>
    <input type="file" id="file-input" />
    <button id="upload-button">Upload File</button>
    <div id="progress-container"></div>
    <script src="https://transloadit.edgly.net/releases/uppy/v1.6.0/uppy.min.js"></script>
    <script>
      var progressBar = new ProgressBar.Circle("#progress-container", {
        strokeWidth: 6,
        easing: "easeInOut",
        duration: 1000,
        color: "#FFEA82",
        trailColor: "#eee",
        trailWidth: 1,
        svgStyle: null,
      });

      var lastUpdateTime = 0; // Thời gian cập nhật cuối cùng
      var uppy = Uppy.Core()
        .use(Uppy.XHRUpload, {
          endpoint: "/upload",
          method: "POST",
          headers: {
            "X-CSRF-Token": "{{ csrf_token() }}",
          },
          chunking: true,
          chunkSize: 1048576,
          formData: true,
          fieldName: "file",
        })
        .on("upload-progress", (file, progress) => {
          const currentTime = Date.now();
          // Kiểm tra xem đã trôi qua 1 giây chưa
          if (currentTime - lastUpdateTime >= 1000) {
            lastUpdateTime = currentTime; // Cập nhật thời gian
            document.getElementById("progress-container").style.display = "block"; // Hiện phần tử tiến trình
            progressBar.animate(progress.bytesUploaded / progress.bytesTotal); // Cập nhật tiến trình
            console.log(
              `File: ${file.name}, Progress: ${
                (progress.bytesUploaded / progress.bytesTotal) * 100
              }%`
            );
          }
        });

      // Kết nối input file với Uppy
      document
        .getElementById("file-input")
        .addEventListener("change", (event) => {
          const files = event.target.files;
          for (let i = 0; i < files.length; i++) {
            uppy.addFile({
              name: files[i].name,
              type: files[i].type,
              data: files[i],
            });
          }
        });

      // Thêm sự kiện click cho nút upload
      document.getElementById("upload-button").addEventListener("click", () => {
        uppy.upload().then((result) => {
          console.log("Upload result:", result);
          document.getElementById("progress-container").style.display = "none"; // Ẩn phần tử tiến trình khi hoàn thành
        });
      });
    </script>
  </body>
</html>
