import { useEffect, useState } from 'react';

/**
 * Hook to detect if an element's content is horizontally truncated
 * @param ref - React ref to the element to check
 * @param depKey - Optional dependency key to trigger re-check
 * @returns boolean indicating if the element is truncated
 */
export function useIsTruncated<T extends HTMLElement>(ref: React.RefObject<T | null>, depKey?: unknown) {
    const [isTruncated, setIsTruncated] = useState(false);

    useEffect(() => {
        const el = ref.current;
        if (!el) return;

        const check = () => setIsTruncated(el.scrollWidth > el.clientWidth);
        check();

        let ro: ResizeObserver | null = null;
        if (typeof ResizeObserver !== 'undefined') {
            ro = new ResizeObserver(() => check());
            ro.observe(el);
        }

        const onResize = () => check();
        window.addEventListener('resize', onResize);

        return () => {
            window.removeEventListener('resize', onResize);
            if (ro) ro.disconnect();
        };
    }, [ref, depKey]);

    return isTruncated;
}
