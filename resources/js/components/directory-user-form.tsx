import { Link, useForm } from '@inertiajs/react';
import { useRef, useState } from 'react';
import type { ChangeEvent, FormEvent } from 'react';
import InputError from '@/components/input-error';
import PhotoCropper from '@/components/photo-cropper';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { readPhoto } from '@/lib/read-photo';
import { index, store, update } from '@/routes/directory-users';

export type DirectoryUserData = {
    id: number;
    first_name: string;
    last_name: string;
    email: string;
    company_id: number;
    department_id: number;
    photo_url: string;
};

export type DirectoryUserFormProps = {
    companies: { id: number; name: string }[];
    departments: { id: number; company_id: number; name: string }[];
    directoryUser?: DirectoryUserData;
};

type FormValues = {
    _method: 'post' | 'patch';
    first_name: string;
    last_name: string;
    email: string;
    company_id: string;
    department_id: string;
    photo: File | null;
};

const textFields = [
    { name: 'first_name', label: 'Nombre', type: 'text', maxLength: 100 },
    { name: 'last_name', label: 'Apellido', type: 'text', maxLength: 100 },
    {
        name: 'email',
        label: 'Correo electrónico',
        type: 'email',
        maxLength: 254,
    },
] as const;

export default function DirectoryUserForm({
    companies,
    departments,
    directoryUser,
}: DirectoryUserFormProps) {
    const editing = directoryUser !== undefined;
    const fileInput = useRef<HTMLInputElement>(null);

    const {
        data,
        setData,
        post,
        processing,
        progress,
        errors,
        setError,
        clearErrors,
    } = useForm<FormValues>({
        _method: editing ? 'patch' : 'post',
        first_name: directoryUser?.first_name ?? '',
        last_name: directoryUser?.last_name ?? '',
        email: directoryUser?.email ?? '',
        company_id: directoryUser?.company_id.toString() ?? '',
        department_id: directoryUser?.department_id.toString() ?? '',
        photo: null,
    });

    const [source, setSource] = useState<string | null>(null);
    const [preview, setPreview] = useState<string | null>(
        directoryUser?.photo_url ?? null,
    );
    const [photoLoading, setPhotoLoading] = useState(false);

    const busy = processing || photoLoading;
    const messages: Record<string, string | undefined> = errors;

    const availableDepartments = departments.filter(
        (department) => department.company_id === Number(data.company_id),
    );

    async function selectPhoto(event: ChangeEvent<HTMLInputElement>) {
        const file = event.currentTarget.files?.[0];
        event.currentTarget.value = '';

        if (!file) {
            return;
        }

        clearErrors('photo');

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

        setPhotoLoading(true);

        try {
            const imageSource = await readPhoto(file);
            const image = new Image();

            image.src = imageSource;
            await image.decode();

            setSource(imageSource);
            setPreview(directoryUser?.photo_url ?? null);
            setData('photo', null);
        } catch {
            setError('photo', 'No se pudo abrir esa imagen. Seleccioná otra.');
        } finally {
            setPhotoLoading(false);
        }
    }

    async function confirmPhoto(photo: File) {
        setPhotoLoading(true);

        try {
            const croppedPreview = await readPhoto(photo);

            setData('photo', photo);
            setPreview(croppedPreview);
            setSource(null);
            clearErrors('photo');
        } catch {
            setError(
                'photo',
                'No se pudo preparar la vista previa. Intentá nuevamente.',
            );
        } finally {
            setPhotoLoading(false);
        }
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (busy) {
            return;
        }

        if (source || (!editing && !data.photo)) {
            setError(
                'photo',
                'Seleccioná una fotografía y confirmá el recorte.',
            );
            return;
        }

        // POST permite enviar el archivo; _method indica la operación a Laravel.
        const url = directoryUser ? update(directoryUser.id).url : store().url;

        post(url, {
            forceFormData: true,
            preserveScroll: true,
        });
    }

    return (
        <form onSubmit={submit} className="space-y-6">
            {messages.save && (
                <p
                    role="alert"
                    className="rounded-lg border border-destructive p-3 text-sm text-destructive"
                >
                    {messages.save}
                </p>
            )}

            <fieldset
                disabled={busy}
                inert={busy}
                className="space-y-6 rounded-xl border p-4 md:p-6"
            >
                <legend className="px-2 font-medium">Datos del usuario</legend>

                <div className="grid gap-4 sm:grid-cols-2">
                    {textFields.map((field) => (
                        <div key={field.name} className="space-y-2">
                            <Label htmlFor={field.name}>{field.label}</Label>
                            <Input
                                id={field.name}
                                name={field.name}
                                type={field.type}
                                maxLength={field.maxLength}
                                value={data[field.name]}
                                required
                                aria-invalid={Boolean(errors[field.name])}
                                aria-describedby={`${field.name}-error`}
                                onChange={(event) =>
                                    setData(field.name, event.target.value)
                                }
                            />
                            <InputError
                                id={`${field.name}-error`}
                                message={errors[field.name]}
                            />
                        </div>
                    ))}

                    <div className="space-y-2">
                        <Label htmlFor="company_id">Empresa</Label>
                        <select
                            id="company_id"
                            value={data.company_id}
                            required
                            aria-invalid={Boolean(errors.company_id)}
                            aria-describedby="company-error"
                            onChange={(event) => {
                                setData({
                                    ...data,
                                    company_id: event.target.value,
                                    department_id: '',
                                });
                            }}
                            className="h-10 w-full rounded-md border bg-background px-3 text-sm"
                        >
                            <option value="">Seleccioná una empresa</option>
                            {companies.map((company) => (
                                <option key={company.id} value={company.id}>
                                    {company.name}
                                </option>
                            ))}
                        </select>
                        <InputError
                            id="company-error"
                            message={errors.company_id}
                        />
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="department_id">Departamento</Label>
                        <select
                            id="department_id"
                            value={data.department_id}
                            required
                            disabled={!data.company_id}
                            aria-invalid={Boolean(errors.department_id)}
                            aria-describedby="department-error"
                            onChange={(event) =>
                                setData('department_id', event.target.value)
                            }
                            className="h-10 w-full rounded-md border bg-background px-3 text-sm disabled:opacity-50"
                        >
                            <option value="">Seleccioná un departamento</option>
                            {availableDepartments.map((department) => (
                                <option
                                    key={department.id}
                                    value={department.id}
                                >
                                    {department.name}
                                </option>
                            ))}
                        </select>
                        <InputError
                            id="department-error"
                            message={errors.department_id}
                        />
                    </div>
                </div>

                <div className="space-y-3">
                    <p className="text-sm font-medium">Fotografía</p>

                    <input
                        ref={fileInput}
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        onChange={selectPhoto}
                        className="hidden"
                        aria-label="Seleccionar fotografía"
                    />

                    <Button
                        type="button"
                        variant="outline"
                        disabled={Boolean(source)}
                        onClick={() => fileInput.current?.click()}
                        aria-describedby="photo-help photo-error"
                    >
                        {preview
                            ? 'Cambiar fotografía'
                            : 'Seleccionar fotografía'}
                    </Button>

                    <p
                        id="photo-help"
                        className="text-sm text-muted-foreground"
                    >
                        JPG, PNG o WebP. Imagen original de hasta 10 MB.
                        {editing
                            ? ' Si no elegís otra, se conserva la fotografía actual.'
                            : ' Seleccioná una imagen y confirmá el recorte.'}
                    </p>

                    {source && (
                        <PhotoCropper
                            key={source}
                            image={source}
                            onConfirm={confirmPhoto}
                            onCancel={() => setSource(null)}
                        />
                    )}

                    {preview && !source && (
                        <figure className="space-y-2">
                            <img
                                src={preview}
                                alt="Fotografía del usuario"
                                width={160}
                                height={160}
                                className="size-40 rounded-lg border object-cover"
                            />
                            <figcaption className="text-sm text-muted-foreground">
                                {data.photo
                                    ? 'Recorte confirmado. Listo para guardar.'
                                    : 'Fotografía actual.'}
                            </figcaption>
                        </figure>
                    )}

                    <InputError id="photo-error" message={errors.photo} />
                </div>
            </fieldset>

            {photoLoading && (
                <p role="status" className="text-sm text-muted-foreground">
                    Preparando fotografía…
                </p>
            )}

            {processing && (
                <p role="status" className="text-sm text-muted-foreground">
                    {progress?.percentage != null
                        ? `Enviando datos: ${progress.percentage}%`
                        : 'Guardando cambios…'}
                </p>
            )}

            <div className="flex gap-3">
                <Button
                    type="submit"
                    disabled={
                        busy || Boolean(source) || (!editing && !data.photo)
                    }
                >
                    {processing
                        ? 'Guardando…'
                        : editing
                          ? 'Guardar cambios'
                          : 'Guardar usuario'}
                </Button>

                <Button type="button" variant="outline" asChild>
                    <Link
                        href={index()}
                        aria-disabled={busy}
                        tabIndex={busy ? -1 : undefined}
                        onClick={(event) => {
                            if (busy) {
                                event.preventDefault();
                            }
                        }}
                        className={busy ? 'pointer-events-none opacity-50' : ''}
                    >
                        Volver
                    </Link>
                </Button>
            </div>
        </form>
    );
}
