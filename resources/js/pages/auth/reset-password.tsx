import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { update } from '@/routes/password';

type Props = {
    token: string;
    email: string;
    passwordRules: string;
};

export default function ResetPassword({ token, email, passwordRules }: Props) {
    return (
        <>
            <Head title="Restablecer contraseña" />

            <Form
                {...update.form()}
                transform={(data) => ({ ...data, token, email })}
                resetOnSuccess={['password', 'password_confirmation']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="email">Correo electrónico</Label>

                            <Input
                                id="email"
                                type="email"
                                name="email"
                                autoComplete="username"
                                value={email}
                                className="h-11 bg-muted"
                                readOnly
                                aria-invalid={Boolean(errors.email)}
                                aria-describedby={
                                    errors.email ? 'email-error' : undefined
                                }
                            />

                            <div id="email-error">
                                <InputError message={errors.email} />
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password">Nueva contraseña</Label>

                            <PasswordInput
                                id="password"
                                name="password"
                                required
                                autoComplete="new-password"
                                className="h-11"
                                autoFocus
                                placeholder="Ingresá tu nueva contraseña"
                                passwordrules={passwordRules}
                                aria-invalid={Boolean(errors.password)}
                                aria-describedby={
                                    errors.password
                                        ? 'password-error'
                                        : undefined
                                }
                            />

                            <div id="password-error">
                                <InputError message={errors.password} />
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password_confirmation">
                                Confirmar contraseña
                            </Label>

                            <PasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                required
                                autoComplete="new-password"
                                className="h-11"
                                placeholder="Repetí la nueva contraseña"
                                passwordrules={passwordRules}
                                aria-invalid={Boolean(
                                    errors.password_confirmation,
                                )}
                                aria-describedby={
                                    errors.password_confirmation
                                        ? 'password-confirmation-error'
                                        : undefined
                                }
                            />

                            <div id="password-confirmation-error">
                                <InputError
                                    message={errors.password_confirmation}
                                />
                            </div>
                        </div>

                        <Button
                            type="submit"
                            className="h-11 w-full"
                            disabled={processing}
                            aria-busy={processing}
                            data-test="reset-password-button"
                        >
                            {processing && <Spinner />}
                            {processing
                                ? 'Guardando…'
                                : 'Guardar nueva contraseña'}
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

ResetPassword.layout = {
    title: 'Restablecer contraseña',
    description: 'Elegí una nueva contraseña y repetila para confirmarla.',
};
