export function readPhoto(file: File): Promise<string> {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();

        reader.onload = () => {
            if (typeof reader.result === 'string') {
                resolve(reader.result);
            } else {
                reject(new Error('No se pudo leer la fotografía.'));
            }
        };

        reader.onerror = () =>
            reject(new Error('No se pudo leer la fotografía.'));

        reader.onabort = () => reject(new Error('La lectura fue cancelada.'));

        reader.readAsDataURL(file);
    });
}
