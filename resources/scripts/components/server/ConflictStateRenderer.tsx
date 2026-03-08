import React, { useState } from 'react';
import { ServerContext } from '@/state/server';
import ScreenBlock from '@/components/elements/ScreenBlock';
import ServerInstallSvg from '@/assets/images/server_installing.svg';
import ServerErrorSvg from '@/assets/images/server_error.svg';
import ServerRestoreSvg from '@/assets/images/server_restore.svg';
import { Button } from '@/components/elements/button';
import { resetStuckInstallation } from '@/api/server/mcsetups/installation';

export default () => {
    const status = ServerContext.useStoreState((state) => state.server.data?.status || null);
    const isTransferring = ServerContext.useStoreState((state) => state.server.data?.isTransferring || false);
    const isNodeUnderMaintenance = ServerContext.useStoreState(
        (state) => state.server.data?.isNodeUnderMaintenance || false
    );
    const uuid = ServerContext.useStoreState((state) => state.server.data?.uuid);
    const getServer = ServerContext.useStoreActions((actions) => actions.server.getServer);
    const [resetting, setResetting] = useState(false);

    const onResetStuck = () => {
        if (!uuid || resetting) return;
        setResetting(true);
        resetStuckInstallation(uuid)
            .then((res) => {
                if (res.success) getServer(uuid);
            })
            .catch(() => {})
            .finally(() => setResetting(false));
    };

    return status === 'installing' || status === 'install_failed' || status === 'reinstall_failed' ? (
        <ScreenBlock
            title={'Running Installer'}
            image={ServerInstallSvg}
            message={'Your server should be ready soon. Open the Console tab to see installation progress (download, extract, etc.). If the installer has finished or is stuck, click below to clear the installing state and restore access.'}
        >
            <Button className="mt-4" onClick={onResetStuck} disabled={resetting}>
                {resetting ? 'Resetting…' : 'Clear installing state (stuck?)'}
            </Button>
        </ScreenBlock>
    ) : status === 'suspended' ? (
        <ScreenBlock
            title={'Server Suspended'}
            image={ServerErrorSvg}
            message={'This server is suspended and cannot be accessed.'}
        />
    ) : isNodeUnderMaintenance ? (
        <ScreenBlock
            title={'Node under Maintenance'}
            image={ServerErrorSvg}
            message={'The node of this server is currently under maintenance.'}
        />
    ) : (
        <ScreenBlock
            title={isTransferring ? 'Transferring' : 'Restoring from Backup'}
            image={ServerRestoreSvg}
            message={
                isTransferring
                    ? 'Your server is being transferred to a new node, please check back later.'
                    : 'Your server is currently being restored from a backup, please check back in a few minutes.'
            }
        />
    );
};
