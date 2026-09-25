import { useId, useState } from 'react';
import Cropper from 'react-easy-crop';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { cropImage } from '@/lib/crop-image';
import type { CropArea } from '@/lib/crop-image';

type PhotoCropperProps = {
    image: string;
    onConfirm: (photo: File) => void;
    onCancel: () => void;
};

export default function PhotoCropper({
    image,
    onConfirm,
    onCancel,
}: PhotoCropperProps) {
    const zoomId = useId();
    const [crop, setCrop] = useState({ x: 0, y: 0 });
    const [zoom, setZoom] = useState(1);
    const [area, setArea] = useState<CropArea | null>(null);
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);

    async function confirmCrop() {
        if (!area || processing) {
            return;
        }

        setProcessing(true);
        setError(null);

        try {
            const photo = await cropImage(image, area);
            onConfirm(photo);
        } catch {
            setError(
                'No se pudo recortar la fotografía. Intentá nuevamente o seleccioná otra imagen.',
            );
        } finally {
            setProcessing(false);
        }
    }

    return (
        <section
            aria-label="Recortar fotografía"
            aria-busy={processing}
            className="space-y-4 rounded-xl border p-4"
        >
            <div>
                <h3 className="font-medium">Ajustá tu fotografía</h3>
                <p className="text-sm text-muted-foreground">
                    Arrastrá la imagen y ajustá el zoom. El recorte será
                    cuadrado.
                </p>
            </div>

            <div
                inert={processing}
                className="relative h-72 overflow-hidden rounded-lg bg-black sm:h-80"
            >
                <Cropper
                    image={image}
                    crop={crop}
                    zoom={zoom}
                    aspect={1}
                    minZoom={1}
                    maxZoom={3}
                    zoomWithScroll={false}
                    onCropChange={setCrop}
                    onZoomChange={setZoom}
                    onCropAreaChange={(_, pixels) => setArea(pixels)}
                />
            </div>

            <div className="space-y-2">
                <Label htmlFor={zoomId}>Zoom: {zoom.toFixed(1)}×</Label>
                <input
                    id={zoomId}
                    type="range"
                    min={1}
                    max={3}
                    step={0.01}
                    value={zoom}
                    disabled={processing}
                    onChange={(event) => setZoom(Number(event.target.value))}
                    className="w-full accent-primary"
                />
            </div>

            {error && (
                <p role="alert" className="text-sm text-destructive">
                    {error}
                </p>
            )}

            <div className="flex flex-wrap gap-2">
                <Button
                    type="button"
                    onClick={confirmCrop}
                    disabled={!area || processing}
                >
                    {processing
                        ? 'Preparando fotografía…'
                        : 'Confirmar recorte'}
                </Button>

                <Button
                    type="button"
                    variant="outline"
                    onClick={onCancel}
                    disabled={processing}
                >
                    Cancelar
                </Button>
            </div>
        </section>
    );
}
