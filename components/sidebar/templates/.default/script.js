document.getElementById('fileUploadButton')?.addEventListener('click', () => {
    document.getElementById('fileUploadTrigger')?.click();
});

document.getElementById('fileUploadTrigger')?.addEventListener('change', function () {
    const files = this.files;
    const folderHash = document.getElementById('folderHash')?.value ?? '';

    if (!files || files.length === 0) {
        return;
    }

    const formData = new FormData();
    for (let i = 0; i < files.length; i++) {
        formData.append('files[]', files[i]);
    }
    formData.append('folderHash', folderHash);

    const loader = document.getElementById('loader');
    if (loader) {
        loader.style.display = 'block';
    }

    fetch('/disk/uploadFile', {
        method: 'POST',
        body: formData
    })
            .then(response => {
                if (loader) {
                    loader.style.display = 'none';
                }

                if (!response.ok) {
                    return response.json().then(data => {
                        throw new Error(data?.error || 'Ошибка загрузки');
                    });
                }

                location.reload();
            })
            .catch(error => {
                if (loader) {
                    loader.style.display = 'none';
                }
                alert(error.message);
            });
});

document.getElementById('createFolderForm')?.addEventListener('submit', function (event) {
    event.preventDefault();
    const folderName = document.getElementById('folderName')?.value.trim() ?? '';
    const parentHash = document.getElementById('currentFolderHash')?.value ?? '';

    if (folderName === '') {
        return;
    }

    fetch('/disk/createFolder', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({name: folderName, parentHash})
    }).then(response => {
        if (response.ok) {
            const modalElement = document.getElementById('createFolderModal');
            const modal = modalElement ? bootstrap.Modal.getInstance(modalElement) : null;
            modal?.hide();
            location.reload();
        } else {
            alert('Ошибка при создании папки.');
        }
    });
});
