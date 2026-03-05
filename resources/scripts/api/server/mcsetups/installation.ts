import http from '@/api/http';

export interface InstallSetupRequest {
    file_id: number | string;
    placeholder_values: Record<string, string>;
    addon_ids?: (number | string)[];
    wipe_data?: boolean;
    zip_and_wipe?: boolean;
}

export const installSetup = (uuid: string, data: InstallSetupRequest): Promise<{ success: boolean; message: string }> => {
    return new Promise((resolve, reject) => {
        http.post(`/api/client/servers/${uuid}/mcsetups/install`, data)
            .then((response) => resolve(response.data))
            .catch(reject);
    });
};

export const resetStuckInstallation = (uuid: string): Promise<{ success: boolean; message?: string; error?: string }> => {
    return http.post(`/api/client/servers/${uuid}/mcsetups/reset-stuck-installation`).then((r) => r.data);
};