import { router } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { destroy } from '@/routes/directory-users';

type Props = {
    user: {
        id: number;
        full_name: string;
    };
};

export default function DeleteDirectoryUser({ user }: Props) {
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState('');
    const cancelButton = useRef<HTMLButtonElement>(null);

    function changeOpen(nextOpen: boolean) {
        if (processing) {
            return;
        }

        setError('');
        setOpen(nextOpen);
    }

    function deleteUser() {
        if (processing) {
            return;
        }

        setError('');
        setProcessing(true);

        router.delete(destroy(user.id).url, {
            preserveScroll: true,
            preserveState: 'errors',
            onSuccess: () => setOpen(false),
            onError: (errors) => {
                setError(
                    errors.deletion ??
                        'No se pudo eliminar el usuario. Intentá nuevamente.',
                );
            },
            onFinish: () => setProcessing(false),
        });
    }

    return (
        <Dialog open={open} onOpenChange={changeOpen}>
            <DialogTrigger asChild>
                <Button
                    type="button"
                    variant="destructive"
                    size="sm"
                    aria-label={`Eliminar a ${user.full_name}`}
                >
                    Eliminar
                </Button>
            </DialogTrigger>

            <DialogContent
                aria-busy={processing}
                onOpenAutoFocus={(event) => {
                    event.preventDefault();
                    cancelButton.current?.focus();
                }}
                onEscapeKeyDown={(event) => {
                    if (processing) {
                        event.preventDefault();
                    }
                }}
                onInteractOutside={(event) => {
                    if (processing) {
                        event.preventDefault();
                    }
                }}
            >
                <DialogHeader>
                    <DialogTitle>¿Eliminar usuario?</DialogTitle>

                    <DialogDescription>
                        Vas a eliminar a{' '}
                        <strong className="font-semibold text-foreground">
                            {user.full_name}
                        </strong>{' '}
                        y su fotografía. Esta acción no se puede deshacer.
                    </DialogDescription>
                </DialogHeader>

                {error && (
                    <p role="alert" className="text-sm text-destructive">
                        {error}
                    </p>
                )}

                <DialogFooter>
                    <Button
                        ref={cancelButton}
                        type="button"
                        variant="outline"
                        disabled={processing}
                        onClick={() => changeOpen(false)}
                    >
                        Cancelar
                    </Button>

                    <Button
                        type="button"
                        variant="destructive"
                        disabled={processing}
                        onClick={deleteUser}
                    >
                        {processing ? 'Eliminando…' : 'Eliminar usuario'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
