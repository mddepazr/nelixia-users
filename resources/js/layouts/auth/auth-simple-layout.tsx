import { Link } from '@inertiajs/react';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <main className="flex min-h-svh items-center justify-center bg-sidebar px-4 py-10 sm:px-6">
            <div className="w-full max-w-md">
                <div className="mb-8 flex justify-center">
                    <Link
                        href={home()}
                        className="flex items-center gap-3 rounded-lg focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-ring"
                        aria-label="Nelixia: inicio"
                    >
                        <div className="flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-sidebar-border bg-white p-1">
                            <img
                                src="/images/nelixia-logo.png"
                                alt=""
                                width={56}
                                height={56}
                                className="size-full object-contain"
                            />
                        </div>

                        <div className="text-sidebar-foreground">
                            <p className="text-2xl font-semibold tracking-tight">
                                Nelixia
                            </p>
                            <p className="text-sm">
                                Administración de usuarios
                            </p>
                        </div>
                    </Link>
                </div>

                <section className="rounded-2xl border border-border bg-card p-6 text-card-foreground shadow-sm sm:p-8">
                    <div className="mb-8 space-y-2">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {title}
                        </h1>

                        {description && (
                            <p className="text-sm leading-relaxed text-muted-foreground">
                                {description}
                            </p>
                        )}
                    </div>

                    {children}
                </section>
            </div>
        </main>
    );
}
