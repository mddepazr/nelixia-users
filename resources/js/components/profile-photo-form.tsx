import { useForm, usePage } from '@inertiajs/react';
import { useRef, useState } from 'react';
import type { ChangeEvent, FormEvent } from 'react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import PhotoCropper from '@/components/photo-cropper';
import { Button } from '@/components/ui/button';
import { readPhoto } from '@/lib/read-photo';
import type { Auth } from '@/types';

export default function ProfilePhotoForm() {
    const { auth } = usePage<{ auth: Auth }>().props;
    const input = useRef<HTMLInputElement>(null);
    const [source, setSource] = useState<string | null>(null);
    const [preview, setPreview] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);
    const {
        data,
        setData,
        post,
        processing,
        progress,
        errors,
        setError,
        clearErrors,
        reset,
    } = useForm<{ photo: File | null }>({ photo: null });

    const busy = loading || processing;
    const photoUrl = preview ?? auth.user.avatar;

    async function selectPhoto(event: ChangeEvent<HTMLInputElement>) {
        const file = event.currentTarget.files?.[0];
        event.currentTarget.value = '';

        if (!file) {
            return;
        }

        clearErrors('photo');
        setData('photo', null);
        setPreview(null);

        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
            setError('photo', 'Seleccioná una imagen JPG, PNG o WebP.');
            return;
        }

        if (file.size > 10 * 1024 * 1024) {
            setError(
                'photo',
                'La imagen original debe pesar como máximo 10 MB.',
            );
            return;
        }

        setLoading(true);

        try {
            const imageSource = await readPhoto(file);
            const image = new Image();
            image.src = imageSource;
            await image.decode();

            setSource(imageSource);
        } catch {
            setError('photo', 'No se pudo abrir esa imagen. Seleccioná otra.');
        } finally {
            setLoading(false);
        }
    }

    async function confirmPhoto(photo: File) {
        setLoading(true);

        try {
            setPreview(await readPhoto(photo));
            setData('photo', photo);
            setSource(null);
            clearErrors('photo');
        } catch {
            setError(
                'photo',
                'No se pudo preparar la vista previa. Intentá nuevamente.',
            );
        } finally {
            setLoading(false);
        }
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (busy) {
            return;
        }

        if (source || !data.photo) {
            setError(
                'photo',
                'Seleccioná una fotografía y confirmá el recorte.',
            );
            return;
        }

        post(ProfileController.updatePhoto().url, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                reset('photo');
                setPreview(null);
            },
        });
    }

    return (
        <section className="space-y-4">
            <Heading
                variant="small"
                title="Foto de perfil"
                description="Tu foto se verá en el menú de la cuenta. Si no agregás una, se mostrarán tus iniciales."
            />

            <form onSubmit={submit} className="space-y-4">
                <input
                    ref={input}
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    onChange={selectPhoto}
                    className="hidden"
                    aria-label="Seleccionar foto de perfil"
                />

                {photoUrl && !source && (
                    <img
                        src={photoUrl}
                        alt="Tu foto de perfil"
                        width={160}
                        height={160}
                        className="size-40 rounded-full border object-cover"
                    />
                )}

                {source && (
                    <PhotoCropper
                        key={source}
                        image={source}
                        onConfirm={confirmPhoto}
                        onCancel={() => setSource(null)}
                    />
                )}

                <div className="flex flex-wrap gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        disabled={busy || Boolean(source)}
                        onClick={() => input.current?.click()}
                    >
                        {photoUrl ? 'Cambiar foto' : 'Seleccionar foto'}
                    </Button>
                    {data.photo && !source && (
                        <Button type="submit" disabled={busy}>
                            {processing ? 'Guardando…' : 'Guardar foto'}
                        </Button>
                    )}
                </div>

                <p className="text-sm text-muted-foreground">
                    JPG, PNG o WebP. Imagen original de hasta 10 MB. Confirmá el
                    recorte cuadrado antes de guardar.
                </p>
                {loading && <p role="status">Preparando fotografía…</p>}
                {processing && (
                    <p role="status">
                        {progress?.percentage != null
                            ? `Enviando fotografía: ${progress.percentage}%`
                            : 'Guardando fotografía…'}
                    </p>
                )}
                <InputError message={errors.photo} />
            </form>
        </section>
    );
}
