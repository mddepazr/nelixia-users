export type CropArea = {
    x: number;
    y: number;
    width: number;
    height: number;
};

const OUTPUT_SIZE = 512;

export async function cropImage(source: string, area: CropArea): Promise<File> {
    const image = new Image();
    image.src = source;

    await image.decode();

    if (area.width <= 0 || area.height <= 0) {
        throw new Error('Seleccioná un área válida para el recorte.');
    }

    const canvas = document.createElement('canvas');
    canvas.width = OUTPUT_SIZE;
    canvas.height = OUTPUT_SIZE;

    const context = canvas.getContext('2d');

    if (!context) {
        throw new Error('El navegador no pudo preparar la fotografía.');
    }

    // JPEG no admite transparencia: usamos un fondo blanco.
    context.fillStyle = '#ffffff';
    context.fillRect(0, 0, OUTPUT_SIZE, OUTPUT_SIZE);

    context.imageSmoothingEnabled = true;
    context.imageSmoothingQuality = 'high';

    context.drawImage(
        image,
        area.x,
        area.y,
        area.width,
        area.height,
        0,
        0,
        OUTPUT_SIZE,
        OUTPUT_SIZE,
    );

    const blob = await new Promise<Blob>((resolve, reject) => {
        canvas.toBlob(
            (result) => {
                if (result) {
                    resolve(result);
                } else {
                    reject(new Error('No se pudo generar el recorte.'));
                }
            },
            'image/jpeg',
            0.9,
        );
    });

    if (blob.size > 2 * 1024 * 1024) {
        throw new Error('La fotografía recortada supera los 2 MB.');
    }

    return new File([blob], 'profile-photo.jpg', {
        type: 'image/jpeg',
    });
}
