import http from '@/api/http';

export interface MCSetupsLicense {
    id: number;
    license_key?: string;
    store_url?: string;
    is_active: boolean;
    expires_at: string | null;
    created_at: string;
    updated_at: string;
}

export const getLicense = (uuid: string, forceRefresh: boolean = false): Promise<MCSetupsLicense | null> => {
    return new Promise((resolve, reject) => {
        const params: any = {};
        if (forceRefresh) {
            params.force_refresh = true;
        }
        http.get(`/api/client/servers/${uuid}/mcsetups/license`, { params })
            .then((response) => resolve(response.data.data))
            .catch(reject);
    });
};