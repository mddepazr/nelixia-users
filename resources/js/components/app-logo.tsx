export default function AppLogo() {
    return (
        <>
            <div className="flex aspect-square size-8 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-sidebar-border bg-white">
                <img
                    src="/images/nelixia-logo.png"
                    alt=""
                    width={32}
                    height={32}
                    className="size-full object-contain p-0.5"
                />
            </div>

            <div className="ml-1 grid min-w-0 flex-1 text-left group-data-[collapsible=icon]:hidden">
                <span className="truncate text-base leading-tight font-semibold tracking-tight">
                    Nelixia
                </span>
                <span className="mt-0.5 truncate text-[11px] leading-tight text-sidebar-foreground/80">
                    Administración de usuarios
                </span>
            </div>
        </>
    );
}
