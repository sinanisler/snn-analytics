async function svgToPng(svgElement) {
    // 1. Get SVG data
    const svgData = new XMLSerializer().serializeToString(svgElement);
    const svgBlob = new Blob([svgData], { type: 'image/svg+xml;charset=utf-8' });
    const url = URL.createObjectURL(svgBlob);

    // 2. Load SVG into image (wrapped in promise)
    const img = await new Promise((resolve, reject) => {
        const image = new Image();
        image.onload = () => resolve(image);
        image.onerror = reject;
        image.src = url;
    });

    // 3. Create canvas and draw
    const canvas = document.createElement('canvas');
    canvas.width = svgElement.width.baseVal.value || svgElement.getBoundingClientRect().width;
    canvas.height = svgElement.height.baseVal.value || svgElement.getBoundingClientRect().height;

    const ctx = canvas.getContext('2d');
    ctx.drawImage(img, 0, 0);

    URL.revokeObjectURL(url);

    // 4. Create PNG image element
    const pngImage = new Image();
    pngImage.src = canvas.toDataURL('image/png');

    return pngImage;
}

module.exports = { svgToPng }
