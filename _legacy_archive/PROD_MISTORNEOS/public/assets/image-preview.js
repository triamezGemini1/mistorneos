(() => {
  function isImageFile(file) {
    return file && file.type && file.type.startsWith('image/');
  }

  function clearNode(node) {
    while (node.firstChild) node.removeChild(node.firstChild);
  }

  function createFileBadge(file) {
    const badge = document.createElement('div');
    badge.className = 'small text-muted';
    badge.textContent = file.name;
    return badge;
  }

  function renderSinglePreview(file, container) {
    clearNode(container);
    if (!file) return;

    if (isImageFile(file)) {
      const img = document.createElement('img');
      img.alt = 'Vista previa';
      img.className = 'img-thumbnail mt-2';
      img.style.maxHeight = '180px';
      img.style.objectFit = 'cover';
      const url = URL.createObjectURL(file);
      img.onload = () => URL.revokeObjectURL(url);
      img.src = url;
      container.appendChild(img);
    } else {
      const box = document.createElement('div');
      box.className = 'mt-2 p-2 border rounded bg-light';
      box.appendChild(createFileBadge(file));
      container.appendChild(box);
    }
  }

  function renderMultiplePreview(files, container) {
    clearNode(container);
    if (!files || !files.length) return;

    const grid = document.createElement('div');
    grid.className = 'row g-3 mt-2';
    Array.from(files).forEach((file) => {
      const col = document.createElement('div');
      col.className = 'col-6 col-md-3';
      const wrap = document.createElement('div');
      wrap.className = 'border rounded p-2 bg-light text-center';
      if (isImageFile(file)) {
        const img = document.createElement('img');
        img.alt = 'Vista previa';
        img.className = 'img-fluid rounded';
        img.style.maxHeight = '140px';
        img.style.objectFit = 'cover';
        const url = URL.createObjectURL(file);
        img.onload = () => URL.revokeObjectURL(url);
        img.src = url;
        wrap.appendChild(img);
      }
      wrap.appendChild(createFileBadge(file));
      col.appendChild(wrap);
      grid.appendChild(col);
    });
    container.appendChild(grid);
  }

  function setupPreviewForInput(input) {
    const targetId = input.getAttribute('data-preview-target');
    if (!targetId) return;
    const container = document.getElementById(targetId);
    if (!container) return;

    const mode = input.getAttribute('data-preview-mode') || (input.multiple ? 'multiple' : 'single');
    input.addEventListener('change', () => {
      if (mode === 'multiple') {
        renderMultiplePreview(input.files, container);
      } else {
        renderSinglePreview(input.files && input.files[0], container);
      }
    });
  }

  function initImagePreviews() {
    document.querySelectorAll('input[type="file"][data-preview-target]').forEach(setupPreviewForInput);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initImagePreviews);
  } else {
    initImagePreviews();
  }
})();
