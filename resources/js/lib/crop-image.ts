export interface PixelCropArea {
    x: number;
    y: number;
    width: number;
    height: number;
}

const MAX_LONG_EDGE = 1600;
const JPEG_QUALITY = 0.85;

function createImage(url: string): Promise<HTMLImageElement> {
    return new Promise((resolve, reject) => {
        const image = new Image();
        image.addEventListener('load', () => resolve(image));
        image.addEventListener('error', reject);
        image.setAttribute('crossOrigin', 'anonymous');
        image.src = url;
    });
}

export async function rotateImageSrc(imageSrc: string, direction: 'cw' | 'ccw'): Promise<string> {
    const image = await createImage(imageSrc);
    const canvas = document.createElement('canvas');
    const degrees = direction === 'cw' ? 90 : -90;
    const radians = (degrees * Math.PI) / 180;

    canvas.width = image.height;
    canvas.height = image.width;

    const ctx = canvas.getContext('2d');
    if (!ctx) {
        throw new Error('Canvas is not supported');
    }

    ctx.translate(canvas.width / 2, canvas.height / 2);
    ctx.rotate(radians);
    ctx.drawImage(image, -image.width / 2, -image.height / 2);

    return new Promise((resolve, reject) => {
        canvas.toBlob(
            (blob) => {
                if (!blob) {
                    reject(new Error('Failed to rotate image'));
                    return;
                }

                resolve(URL.createObjectURL(blob));
            },
            'image/jpeg',
            JPEG_QUALITY,
        );
    });
}

export async function getCroppedImageFile(
    imageSrc: string,
    pixelCrop: PixelCropArea,
    fileName = 'receipt-cropped.jpg',
): Promise<File> {
    const image = await createImage(imageSrc);
    const canvas = document.createElement('canvas');

    let { width, height } = pixelCrop;
    const longEdge = Math.max(width, height);

    if (longEdge > MAX_LONG_EDGE) {
        const scale = MAX_LONG_EDGE / longEdge;
        width = Math.round(width * scale);
        height = Math.round(height * scale);
    }

    canvas.width = width;
    canvas.height = height;

    const ctx = canvas.getContext('2d');
    if (!ctx) {
        throw new Error('Canvas is not supported');
    }

    ctx.drawImage(image, pixelCrop.x, pixelCrop.y, pixelCrop.width, pixelCrop.height, 0, 0, width, height);

    return new Promise((resolve, reject) => {
        canvas.toBlob(
            (blob) => {
                if (!blob) {
                    reject(new Error('Failed to crop image'));
                    return;
                }

                resolve(new File([blob], fileName, { type: 'image/jpeg' }));
            },
            'image/jpeg',
            JPEG_QUALITY,
        );
    });
}
