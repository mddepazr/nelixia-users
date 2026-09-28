import type { ImgHTMLAttributes } from 'react';

export default function AppLogoIcon(
    props: ImgHTMLAttributes<HTMLImageElement>,
) {
    return (
        <img
            src="/images/nelixia-mark.svg"
            alt=""
            width={40}
            height={40}
            {...props}
        />
    );
}
