import { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path
                fillRule="evenodd"
                clipRule="evenodd"
                d="M8 4V20H10V16H12C14.76 16 17 13.76 17 11C17 8.24 14.76 6 12 6H8ZM12 14H10V8H12C13.65 8 15 9.35 15 11C15 12.65 13.65 14 12 14Z"
            />
        </svg>
    );
}
