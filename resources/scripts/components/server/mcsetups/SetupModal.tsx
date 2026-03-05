import React from 'react';
import { Dialog } from '@/components/elements/dialog';
import { Button } from '@/components/elements/button';
import tw from 'twin.macro';
import { StoreFile, Addon } from '@/api/server/mcsetups/store';
import { LuPackage, LuDownload, LuFileText, LuUser } from 'react-icons/lu';

interface Props {
    open: boolean;
    onClose(): void;
    file: StoreFile | null;
    onInstall: () => void;
}

const SetupModal = ({ open, onClose, file, onInstall }: Props) => {
    if (!file) return null;

    const groupedAddons = file.addons.reduce((acc, addon) => {
        const type = addon.file_type || 'Other';
        if (!acc[type]) {
            acc[type] = [];
        }
        acc[type].push(addon);
        return acc;
    }, {} as Record<string, Addon[]>);

    return (
        <Dialog open={open} onClose={onClose} title={file.display_name}>
            <div css={tw`space-y-4`}>
                {file.cover_image_url && (
                    <img
                        src={file.cover_image_url}
                        alt={file.display_name}
                        css={tw`w-full h-48 object-cover rounded-lg`}
                    />
                )}

                <div>
                    <h3 css={tw`text-lg font-semibold text-white mb-2`}>
                        Description
                    </h3>
                    <p css={tw`text-sm text-neutral-200`}>
                        {file.description}
                    </p>
                </div>

                <div css={tw`grid grid-cols-2 gap-4`}>
                    <div>
                        <div css={tw`flex items-center gap-2 text-sm text-neutral-300 mb-1`}>
                            <LuUser css={tw`w-4 h-4`} />
                            Author
                        </div>
                        <p css={tw`text-sm text-white`}>
                            {file.author_name}
                        </p>
                    </div>

                    {file.version && (
                        <div>
                            <div css={tw`flex items-center gap-2 text-sm text-neutral-300 mb-1`}>
                                <LuPackage css={tw`w-4 h-4`} />
                                Version
                            </div>
                            <p css={tw`text-sm text-white`}>
                                {file.version}
                            </p>
                        </div>
                    )}
                </div>

                {file.addons.length > 0 && (
                    <div>
                        <h3 css={tw`text-lg font-semibold text-white mb-2`}>
                            Required Addons
                        </h3>
                        <div css={tw`space-y-3`}>
                            {Object.entries(groupedAddons).map(([type, addons]) => (
                                <div key={type}>
                                    <h4 css={tw`text-sm font-medium text-neutral-200 mb-2`}>
                                        {type} ({addons.length})
                                    </h4>
                                    <div css={tw`space-y-2`}>
                                        {addons.map((addon) => (
                                            <div key={addon.id} css={tw`bg-neutral-600 rounded p-3 border border-neutral-500`}>
                                                <p css={tw`text-sm text-white font-medium`}>
                                                    {addon.display_name}
                                                </p>
                                                {addon.description && (
                                                    <p css={tw`text-xs text-neutral-300 mt-1`}>
                                                        {addon.description}
                                                    </p>
                                                )}
                                                <p css={tw`text-xs text-neutral-400 mt-1`}>
                                                    Location: <code css={tw`text-neutral-300`}>{addon.file_location}</code>
                                                </p>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {file.release_notes && (
                    <div>
                        <h3 css={tw`text-lg font-semibold text-white mb-2 flex items-center gap-2`}>
                            <LuFileText css={tw`w-4 h-4`} />
                            Release Notes
                        </h3>
                        <div css={tw`bg-neutral-600 rounded p-3 border border-neutral-500`}>
                            <p css={tw`text-sm text-neutral-200 whitespace-pre-wrap`}>
                                {file.release_notes}
                            </p>
                        </div>
                    </div>
                )}
            </div>

            <Dialog.Footer>
                <Button onClick={onInstall}>
                    <LuDownload css={tw`w-4 h-4 mr-2`} />
                    Install Setup
                </Button>
            </Dialog.Footer>
        </Dialog>
    );
};

export default SetupModal;

