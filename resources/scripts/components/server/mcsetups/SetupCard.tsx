import React from 'react';
import tw from 'twin.macro';
import { Button } from '@/components/elements/button';
import { StoreFile } from '@/api/server/mcsetups/store';
import { LuDownload, LuPackage, LuInfo } from 'react-icons/lu';
import styled from 'styled-components/macro';

const Card = styled.div`
    ${tw`bg-neutral-700 rounded-lg shadow-md border border-neutral-600 hover:border-neutral-500 transition-colors flex flex-col h-full`};
`;

interface Props {
    file: StoreFile;
    onInstall: (file: StoreFile) => void;
    onViewDetails?: (file: StoreFile) => void;
    storeUrl?: string;
}

const SetupCard = ({ file, onInstall, onViewDetails, storeUrl }: Props) => {
    const getImageUrl = (url: string | null | undefined): string | null => {
        if (!url || url.trim() === '') return null;
        if (url.startsWith('http://') || url.startsWith('https://')) {
            return url;
        }
        if (storeUrl && url.startsWith('/')) {
            const baseUrl = storeUrl.replace(/\/+$/, '');
            return `${baseUrl}${url}`;
        }
        return url;
    };

    const imageUrl = getImageUrl(file.cover_image_url);

    return (
        <Card css={tw`p-4`}>
            {imageUrl ? (
                <div css={tw`w-full h-48 bg-neutral-600 rounded mb-4 overflow-hidden relative`}>
                    <img
                        src={imageUrl}
                        alt={file.display_name}
                        css={tw`w-full h-full object-cover`}
                        onError={(e) => {
                            const target = e.target as HTMLImageElement;
                            target.style.display = 'none';
                            const fallback = target.parentElement?.querySelector('[data-fallback]') as HTMLElement;
                            if (fallback) {
                                fallback.style.display = 'flex';
                            }
                        }}
                    />
                    <div data-fallback css={tw`absolute inset-0 hidden items-center justify-center bg-neutral-600`}>
                        <LuPackage css={tw`w-16 h-16 text-neutral-300`} />
                    </div>
                </div>
            ) : (
                <div css={tw`w-full h-48 bg-neutral-600 rounded mb-4 flex items-center justify-center border-2 border-neutral-500`}>
                    <LuPackage css={tw`w-16 h-16 text-neutral-300`} />
                </div>
            )}
            
            <h3 css={tw`text-base font-semibold text-white mb-1`}>
                {file.display_name}
            </h3>
            
            <p css={tw`text-sm text-neutral-200 mb-2 line-clamp-2 flex-1`}>
                {file.description}
            </p>
            
            <div css={tw`flex items-center justify-between mb-4`}>
                <span css={tw`text-xs text-neutral-300`}>
                    By {file.author_name}
                </span>
                {file.version && (
                    <span css={tw`text-xs text-neutral-400 bg-neutral-600 px-2 py-1 rounded`}>
                        v{file.version}
                    </span>
                )}
            </div>

            <div css={tw`flex gap-2 mt-auto`}>
                {onViewDetails && (
                    <Button
                        onClick={() => onViewDetails(file)}
                        variant={Button.Variants.Secondary}
                        css={tw`flex-1 hover:!bg-transparent hover:!text-gray-50 hover:!opacity-100`}
                    >
                        <LuInfo css={tw`w-4 h-4 mr-2`} />
                        Details
                    </Button>
                )}
                <Button
                    onClick={() => onInstall(file)}
                    css={tw`flex-1 bg-blue-500`}
                >
                    <LuDownload css={tw`w-4 h-4 mr-2`} />
                    Install
                </Button>
            </div>
        </Card>
    );
};

export default SetupCard;

