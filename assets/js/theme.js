document.addEventListener('DOMContentLoaded', function(){
  function setupArea(prefix){
    const area = document.getElementById(prefix + '_area');
    if (!area) return;
    const fileInput = document.querySelector('input[name="' + prefix + '"]');
    const preview = document.getElementById(prefix + '_preview');
    const focalX = document.getElementById(prefix + '_focal_x');
    const focalY = document.getElementById(prefix + '_focal_y');

    area.addEventListener('dragover', e=>{ e.preventDefault(); area.classList.add('drag'); });
    area.addEventListener('dragleave', e=>{ area.classList.remove('drag'); });
    area.addEventListener('drop', e=>{ e.preventDefault(); area.classList.remove('drag'); const f = e.dataTransfer.files[0]; if (f) handleFile(f); });

    if (fileInput) fileInput.addEventListener('change', e=>{ const f = e.target.files[0]; if (f) handleFile(f); });

    function handleFile(f){
      if (!f.type.startsWith('image/')) return;
      const reader = new FileReader();
      reader.onload = function(){ showPreview(reader.result); }
      reader.readAsDataURL(f);
    }

    function showPreview(dataUrl){
      preview.innerHTML = '';
      const img = new Image(); img.src = dataUrl; img.style.maxWidth='100%'; img.style.cursor='crosshair';
      preview.appendChild(img);

      // allow focal point selection
      img.addEventListener('click', function(ev){
        const r = img.getBoundingClientRect();
        const x = (ev.clientX - r.left) / r.width;
        const y = (ev.clientY - r.top) / r.height;
        focalX.value = x.toFixed(3);
        focalY.value = y.toFixed(3);
        placeMarker(preview, x, y);
      });

      // draw existing focal if present
      const fx = parseFloat(focalX.value || 0);
      const fy = parseFloat(focalY.value || 0);
      if (fx || fy) placeMarker(preview, fx, fy);

      // crop button: if Cropper available, open modal for interactive crop, else fallback to simple cover crop
      const cropBtn = document.createElement('button'); cropBtn.type='button'; cropBtn.textContent='Recortar / Usar'; cropBtn.className='btn';
      cropBtn.style.display='block'; cropBtn.style.marginTop='8px';
      cropBtn.addEventListener('click', function(){
        if (window.Cropper) {
          // open modal and init cropper
          const modal = document.getElementById('cropper_modal');
          const container = document.getElementById('cropper_container');
          container.innerHTML = '';
          const imgEl = new Image(); imgEl.src = dataUrl; imgEl.style.maxWidth = '100%'; container.appendChild(imgEl);
          modal.style.display = 'flex';
          // initialize cropper
          const aspect = (prefix === 'banner') ? (1200/300) : 1;
          const cropper = new Cropper(imgEl, { aspectRatio: aspect, viewMode: 1 });

          const applyBtn = document.getElementById('cropper_apply');
          const cancelBtn = document.getElementById('cropper_cancel');

          function clean() { try { cropper.destroy(); } catch(e){}; modal.style.display='none'; container.innerHTML=''; }

          cancelBtn.onclick = function(){ clean(); };
          applyBtn.onclick = function(){
            const canvas = cropper.getCroppedCanvas({ width: prefix==='banner'?1200:400, height: prefix==='banner'?300:400, imageSmoothingQuality:'high' });
            const outData = canvas.toDataURL('image/jpeg', 0.9);
            const hidden = document.querySelector('input[name="cropped_' + prefix + '_data"]');
            if (hidden) hidden.value = outData;
            if (fileInput) { try { fileInput.value = ''; } catch(e){} }
            clean();
            cropBtn.textContent='Imagen lista';
          };
        } else {
          // fallback simple cover crop
          const isBanner = prefix === 'banner';
          const cw = isBanner ? 1200 : 400; const ch = isBanner ? 300 : 400;
          const canvas = document.createElement('canvas');
          canvas.width = cw; canvas.height = ch;
          const ctx = canvas.getContext('2d');
          const imgEl = preview.querySelector('img');
          const iw = imgEl.naturalWidth, ih = imgEl.naturalHeight;
          const scale = Math.max(cw/iw, ch/ih);
          const dw = iw*scale, dh = ih*scale;
          const dx = (cw - dw)/2, dy = (ch - dh)/2;
          ctx.drawImage(imgEl, dx, dy, dw, dh);
          const dataUrl = canvas.toDataURL('image/jpeg', 0.9);
          const hidden = document.querySelector('input[name="cropped_' + prefix + '_data"]');
          if (hidden) hidden.value = dataUrl;
          if (fileInput) { try { fileInput.value = ''; } catch(e){} }
          cropBtn.textContent='Imagen lista';
        }
      });
      preview.appendChild(cropBtn);
    }

    function placeMarker(container, x, y){
      let m = container.querySelector('.focal-marker');
      if (!m){ m = document.createElement('div'); m.className='focal-marker'; m.style.position='absolute'; m.style.width='12px'; m.style.height='12px'; m.style.background='rgba(255,255,255,.9)'; m.style.border='2px solid rgba(0,0,0,.7)'; m.style.borderRadius='50%'; m.style.transform='translate(-50%,-50%)'; m.style.zIndex=10; container.style.position='relative'; container.appendChild(m); }
      const img = container.querySelector('img');
      if (!img) return;
      const r = img.getBoundingClientRect();
      const left = (r.width * x) + img.offsetLeft;
      const top = (r.height * y) + img.offsetTop;
      m.style.left = (x*100) + '%'; m.style.top = (y*100) + '%';
    }
  }

  setupArea('logo');
  setupArea('banner');
});
