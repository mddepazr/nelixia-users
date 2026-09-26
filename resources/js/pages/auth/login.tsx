import { Form, Head } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type LoginThrottle = {
    retryAt: number;
    retryAfter: number;
};

type Props = {
    status?: string;
    canResetPassword: boolean;
    loginThrottle?: LoginThrottle | null;
};

function useLoginCountdown(throttle: LoginThrottle | null) {
    const [currentTime, setCurrentTime] = useState(0);
    const retryAt = throttle?.retryAt ?? 0;

    useEffect(() => {
        if (retryAt === 0) {
            return;
        }

        // Usamos la hora de vencimiento para evitar acumular retrasos.
        const interval = window.setInterval(() => {
            const now = Date.now();

            setCurrentTime(now);

            if (now >= retryAt) {
                window.clearInterval(interval);
            }
        }, 250);

        return () => window.clearInterval(interval);
    }, [retryAt]);

    if (!throttle) {
        return 0;
    }

    return Math.max(
        0,
        Math.min(
            throttle.retryAfter,
            Math.ceil((throttle.retryAt - currentTime) / 1000),
        ),
    );
}

export default function Login({
    status,
    canResetPassword,
    loginThrottle = null,
}: Props) {
    const remainingSeconds = useLoginCountdown(loginThrottle);
    const isBlocked = remainingSeconds > 0;

    return (
        <>
            <Head title="Iniciar sesión" />

            {status && (
                <div
                    role="status"
                    className="mb-6 rounded-lg border border-border bg-muted px-4 py-3 text-sm text-foreground"
                >
                    {status}
                </div>
            )}

            {isBlocked && (
                <div
                    id="login-throttle"
                    className="mb-6 rounded-lg border border-border bg-muted px-4 py-3 text-sm text-foreground"
                >
                    <p className="font-medium">
                        Realizaste demasiados intentos.
                    </p>

                    <p className="mt-1 text-muted-foreground">
                        Intentá nuevamente en{' '}
                        <span
                            role="timer"
                            aria-live="off"
                            className="font-semibold text-foreground tabular-nums"
                        >
                            {remainingSeconds}
                        </span>{' '}
                        {remainingSeconds === 1 ? 'segundo' : 'segundos'}.
                    </p>
                </div>
            )}

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => {
                    // El contador sustituye el mensaje estático del bloqueo.
                    const emailError = loginThrottle ? undefined : errors.email;

                    return (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="email">
                                    Correo electrónico
                                </Label>

                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    autoFocus
                                    autoComplete="username"
                                    placeholder="nombre@empresa.com"
                                    className="h-11"
                                    aria-invalid={Boolean(emailError)}
                                    aria-describedby={
                                        emailError ? 'email-error' : undefined
                                    }
                                />

                                <div id="email-error">
                                    <InputError message={emailError} />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">Contraseña</Label>

                                <PasswordInput
                                    id="password"
                                    name="password"
                                    required
                                    autoComplete="current-password"
                                    placeholder="Ingresá tu contraseña"
                                    className="h-11"
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

                            <div className="flex flex-wrap items-center justify-between gap-4">
                                <div className="flex items-center gap-2">
                                    <Checkbox id="remember" name="remember" />

                                    <Label
                                        htmlFor="remember"
                                        className="cursor-pointer font-normal"
                                    >
                                        Recordarme
                                    </Label>
                                </div>

                                {canResetPassword && (
                                    <TextLink
                                        href={request()}
                                        className="text-sm"
                                    >
                                        ¿Olvidaste tu contraseña?
                                    </TextLink>
                                )}
                            </div>

                            <Button
                                type="submit"
                                className="h-11 w-full"
                                disabled={processing || isBlocked}
                                aria-busy={processing}
                                aria-describedby={
                                    isBlocked ? 'login-throttle' : undefined
                                }
                                data-test="login-button"
                            >
                                {processing && <Spinner />}

                                {processing
                                    ? 'Ingresando…'
                                    : isBlocked
                                      ? 'Esperá para intentar nuevamente'
                                      : 'Iniciar sesión'}
                            </Button>
                        </>
                    );
                }}
            </Form>
        </>
    );
}

Login.layout = {
    title: 'Iniciar sesión',
    description:
        'Ingresá con tu cuenta de administrador para gestionar los usuarios.',
};
