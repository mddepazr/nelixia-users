import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { email } from '@/routes/password';

export default function ForgotPassword({ status }: { status?: string }) {
    return (
        <>
            <Head title="Recuperar contraseña" />

            {status && (
                <div
                    role="status"
                    className="mb-6 rounded-lg border border-border bg-muted px-4 py-3 text-sm text-foreground"
                >
                    {status}
                </div>
            )}

            <Form {...email.form()} className="flex flex-col gap-6">
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="email">Correo electrónico</Label>

                            <Input
                                id="email"
                                type="email"
                                name="email"
                                required
                                autoComplete="email"
                                autoFocus
                                placeholder="nombre@empresa.com"
                                className="h-11"
                                aria-invalid={Boolean(errors.email)}
                                aria-describedby={
                                    errors.email ? 'email-error' : undefined
                                }
                            />

                            <div id="email-error">
                                <InputError message={errors.email} />
                            </div>
                        </div>

                        <Button
                            type="submit"
                            className="h-11 w-full"
                            disabled={processing}
                            aria-busy={processing}
                            data-test="email-password-reset-link-button"
                        >
                            {processing && <Spinner />}
                            {processing ? 'Enviando…' : 'Enviar enlace'}
                        </Button>
                    </>
                )}
            </Form>

            <div className="mt-6 text-center text-sm">
                <TextLink href={login()}>Volver a iniciar sesión</TextLink>
            </div>
        </>
    );
}

ForgotPassword.layout = {
    title: 'Recuperar contraseña',
    description:
        'Ingresá el correo de tu cuenta para solicitar un enlace de recuperación.',
};
