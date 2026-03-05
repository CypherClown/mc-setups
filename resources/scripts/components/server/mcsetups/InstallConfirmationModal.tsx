import React, { useState, useEffect } from 'react';
import { Dialog } from '@/components/elements/dialog';
import { Button } from '@/components/elements/button';
import Switch from '@/components/elements/Switch';
import tw from 'twin.macro';
import Alert from '@/components/elements/alert/Alert';
import { StoreFile } from '@/api/server/mcsetups/store';
import { ServerContext } from '@/state/server';
import loadDirectory from '@/api/server/files/loadDirectory';
import compressFiles from '@/api/server/files/compressFiles';
import deleteFiles from '@/api/server/files/deleteFiles';
import renameFiles from '@/api/server/files/renameFiles';
import { httpErrorToHuman } from '@/api/http';

interface Props {
    open: boolean;
    file: StoreFile | null;
    onClose: () => void;
    onConfirm: (wipeData: boolean, zipAndWipe: boolean) => Promise<void>;
}

const InstallConfirmationModal = ({ open, file, onClose, onConfirm }: Props) => {
    const uuid = ServerContext.useStoreState(state => state.server.data!.uuid);
    const serverName = ServerContext.useStoreState(state => state.server.data!.name);
    const [wipeData, setWipeData] = useState(false);
    const [zipAndWipe, setZipAndWipe] = useState(false);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (zipAndWipe && wipeData) {
            setWipeData(false);
        }
    }, [zipAndWipe]);

    useEffect(() => {
        if (wipeData && zipAndWipe) {
            setZipAndWipe(false);
        }
    }, [wipeData]);

    const handleConfirm = async () => {
        if (!file) return;

        setLoading(true);
        setError(null);

        let zipFileName: string | null = null;

        try {
            if (zipAndWipe) {
                try {
                    const files = await loadDirectory(uuid, '/');
                    const archiveExtensions = /\.(zip|rar|tar|gz|bz2|7z|xz|zst|br|lz4|sz)$/i;
                    const fileNames = files
                        .map(f => f.name)
                        .filter(name => !archiveExtensions.test(name));
                    
                    if (fileNames.length > 0) {
                        const zipFile = await compressFiles(uuid, '/', fileNames);
                        const sanitizedServerName = serverName.replace(/[^a-zA-Z0-9._-]/g, '_');
                        const timestamp = new Date().toISOString().replace(/[:.]/g, '-').slice(0, -5);
                        zipFileName = `${sanitizedServerName}_backup_${timestamp}.zip`;
                        
                        if (zipFile.name !== zipFileName) {
                            await renameFiles(uuid, '/', [{
                                from: zipFile.name,
                                to: zipFileName
                            }]);
                        }
                    }
                } catch (err: any) {
                    console.error('Failed to create archive:', err);
                    setError('Failed to create archive: ' + httpErrorToHuman(err));
                    setLoading(false);
                    return;
                }
            }

            if (wipeData && !zipAndWipe) {
                try {
                    const files = await loadDirectory(uuid, '/');
                    const archiveExtensions = /\.(zip|rar|tar|gz|bz2|7z|xz|zst|br|lz4|sz)$/i;
                    const fileNames = files
                        .map(f => f.name)
                        .filter(name => !archiveExtensions.test(name));
                    
                    if (fileNames.length > 0) {
                        await deleteFiles(uuid, '/', fileNames);
                    }
                } catch (err: any) {
                    console.error('Failed to delete files:', err);
                    setError('Failed to delete files: ' + httpErrorToHuman(err));
                    setLoading(false);
                    return;
                }
            }

            if (zipAndWipe && zipFileName) {
                try {
                    const files = await loadDirectory(uuid, '/');
                    const archiveExtensions = /\.(zip|rar|tar|gz|bz2|7z|xz|zst|br|lz4|sz)$/i;
                    const fileNames = files
                        .map(f => f.name)
                        .filter(name => {
                            if (name === zipFileName) {
                                return false;
                            }
                            return !archiveExtensions.test(name);
                        });
                    
                    if (fileNames.length > 0) {
                        await deleteFiles(uuid, '/', fileNames);
                    }
                } catch (err: any) {
                    console.error('Failed to delete files after zipping:', err);
                    setError('Failed to delete files after zipping: ' + httpErrorToHuman(err));
                    setLoading(false);
                    return;
                }
            }

            await onConfirm(wipeData, zipAndWipe);
            
            setWipeData(false);
            setZipAndWipe(false);
        } catch (err: any) {
            setError(httpErrorToHuman(err));
        } finally {
            setLoading(false);
        }
    };

    const handleClose = () => {
        setWipeData(false);
        setZipAndWipe(false);
        setError(null);
        onClose();
    };

    const handleWipeDataChange = (checked: boolean) => {
        if (checked) {
            setZipAndWipe(false);
            setWipeData(true);
        } else {
            setWipeData(false);
        }
    };

    const handleZipAndWipeChange = (checked: boolean) => {
        if (checked) {
            setWipeData(false);
            setZipAndWipe(true);
        } else {
            setZipAndWipe(false);
        }
    };

    if (!file) {
        return null;
    }

    return (
        <Dialog
            title="Confirm MCSetups Installation"
            open={open}
            onClose={handleClose}
        >
            <div css={tw`space-y-4`}>
                <p css={tw`text-sm`}>
                    You are about to install the following setup:
                </p>
                
                <div css={tw`bg-neutral-700 rounded p-3`}>
                    <div>
                        <span>Setup: </span>
                        <strong>{file.display_name}</strong>
                    </div>
                    {file.author_name && (
                        <div css={tw`mt-2`}>
                            <span>Author: </span>
                            <strong>{file.author_name.trim()}</strong>
                        </div>
                    )}
                    {file.version && (
                        <div css={tw`mt-2`}>
                            <span>Version: </span>
                            <strong>v{file.version}</strong>
                        </div>
                    )}
                    {file.size > 0 && (
                        <div css={tw`mt-2`}>
                            <span>Size: </span>
                            <strong>{(file.size / 1024 / 1024).toFixed(2)} MB</strong>
                        </div>
                    )}
                </div>

                <div css={tw`border border-neutral-700 rounded p-3`} key={`zip-${zipAndWipe}-${wipeData}`}>
                    <Switch
                        name="zipAndWipe"
                        label="ZIP AND WIPE SERVER DATA"
                        description="Archive all existing files into a zip file before removing them and installing the new setup. This preserves your current server files as a backup."
                        defaultChecked={zipAndWipe}
                        readOnly={wipeData}
                        onChange={(e) => handleZipAndWipeChange(e.target.checked)}
                    />
                </div>

                <div css={tw`border border-neutral-700 rounded p-3`} key={`wipe-${wipeData}-${zipAndWipe}`}>
                    <Switch
                        name="wipeData"
                        label="WIPE SERVER DATA"
                        description="Remove all existing files and folders from the server before installing. This action cannot be undone."
                        defaultChecked={wipeData}
                        readOnly={zipAndWipe}
                        onChange={(e) => handleWipeDataChange(e.target.checked)}
                    />
                </div>

                {error && (
                    <Alert type="danger" className="mt-4">
                        {error}
                    </Alert>
                )}

                {zipAndWipe && (
                    <Alert type="warning" className="mt-4">
                        All existing files will be archived to a zip file before being removed.
                    </Alert>
                )}

                {wipeData && !zipAndWipe && (
                    <Alert type="danger" className="mt-4">
                        Warning: All existing files will be permanently deleted. This action cannot be undone.
                    </Alert>
                )}
            </div>

            <Dialog.Footer>
                <Button
                    onClick={handleConfirm}
                    disabled={loading}
                >
                    {loading ? 'Processing...' : 'Install Setup'}
                </Button>
            </Dialog.Footer>
        </Dialog>
    );
};

export default InstallConfirmationModal;
