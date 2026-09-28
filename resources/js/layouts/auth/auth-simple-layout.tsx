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
                        className="flex flex-col items-center gap-1 rounded-lg focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-ring"
                        aria-label="Nelixia: inicio"
                    >
                        <img
                            src="/images/nelixia-logo.svg"
                            alt="Nelixia"
                            width={154}
                            height={112}
                            className="h-28 w-auto object-contain"
                        />
                        <span className="text-sm text-sidebar-foreground/80">
                            Administración de usuarios
                        </span>
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
