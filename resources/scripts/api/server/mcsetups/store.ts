import http from '@/api/http';

export interface Placeholder {
    token: string;
    label: string;
    description: string;
    example: string;
}

export interface Addon {
    id: number;
    display_name: string;
    description: string;
    source_name: string | null;
    requires_license: boolean;
    file_type: string;
    file_location: string;
}

export interface StoreFile {
    id: number | string;
    filename: string;
    display_name: string;
    description: string;
    cover_image_url: string | null;
    product_link: string | null;
    author_name: string;
    source_name: string | null;
    price: number;
    size: number;
    is_zip: boolean;
    product_name: string | null;
    requires_license: boolean;
    placeholders: Placeholder[];
    version: string | null;
    release_notes: string | null;
    addons: Addon[];
    game_version?: string | null;
    product_category?: string | null;
    is_client_upload?: boolean;
}

export interface StoreFiltersResponse {
    success: boolean;
    data: {
        game_versions: string[];
        categories: string[];
    };
}

export interface StoreFilesResponse {
    success: boolean;
    data: {
        files: StoreFile[];
        next_offset?: number;
    };
}

export interface StoreFilesListParams {
    search?: string;
    category?: string;
    game_version?: string;
    limit?: number;
    offset?: number;
    force_refresh?: boolean;
}


const buildStoreUrl = (baseUrl: string, path: string): string => {
    const cleanBase = baseUrl.replace(/\/+$/, '');
    return `${cleanBase}/store${path}`;
};

export const validateLicense = async (storeUrl: string, licenseKey: string): Promise<{ success: boolean; message?: string }> => {
    try {
        const response = await fetch(buildStoreUrl(storeUrl, '/validate'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                license_key: licenseKey,
            }),
        });

        const data = await response.json();
        return { success: response.ok && data.success === true };
    } catch (error) {
        return { success: false, message: 'Failed to validate license' };
    }
};

export interface LicenseValidationResult {
    success: boolean;
    error?: string;
    errorType?: 'network' | 'validation' | 'server' | 'unknown';
    httpStatus?: number;
}

export const validateLicenseViaBackend = async (uuid: string, storeUrl: string, licenseKey: string, forceRefresh: boolean = false): Promise<LicenseValidationResult> => {
    return new Promise((resolve) => {
        const url = `/api/client/servers/${uuid}/mcsetups/validate-license`;
        
        http.post(url, { force_refresh: forceRefresh })
            .then((response) => {
                const success = response.data?.success === true;

                if (success) {
                    resolve({ success: true });
                } else {
                    const errorMessage = response.data?.message || response.data?.reason || response.data?.error || 'License validation failed';
                    resolve({
                        success: false,
                        error: errorMessage,
                        errorType: 'validation',
                        httpStatus: response.status,
                    });
                }
            })
            .catch((error) => {
                const httpStatus = error.response?.status;
                let errorMessage = error.response?.data?.message ||
                    error.response?.data?.reason ||
                    error.response?.data?.error ||
                    error.message ||
                    'Failed to validate license';

                let errorType: 'network' | 'validation' | 'server' | 'unknown' = 'unknown';
                if (!error.response) {
                    errorType = 'network';
                    errorMessage = 'Web server is not reachable. Check your connection and try again.';
                } else if (httpStatus >= 500) {
                    errorType = 'server';
                    errorMessage = 'Store server is temporarily unavailable. Try again later.';
                } else if (httpStatus >= 400) {
                    errorType = 'validation';
                }

                resolve({
                    success: false,
                    error: errorMessage,
                    errorType,
                    httpStatus,
                });
            });
    });
};

export const getStoreFiles = async (
    uuid: string,
    forceRefresh: boolean = false,
    listParams?: StoreFilesListParams
): Promise<StoreFile[]> => {
    const result = await getStoreFilesPage(uuid, { ...listParams, force_refresh: forceRefresh });
    return result.files;
};

export const getStoreFilesPage = async (
    uuid: string,
    params?: StoreFilesListParams
): Promise<{ files: StoreFile[]; nextOffset?: number }> => {
    return new Promise((resolve, reject) => {
        const query: Record<string, string | number | boolean | undefined> = {};
        if (params?.force_refresh) query.force_refresh = true;
        if (params?.search != null) query.search = params.search;
        if (params?.category != null) query.category = params.category;
        if (params?.game_version != null) query.game_version = params.game_version;
        if (params?.limit != null) query.limit = params.limit;
        if (params?.offset != null) query.offset = params.offset;

        http.get(`/api/client/servers/${uuid}/mcsetups/store/files`, { params: query })
            .then((response) => {
                const data: StoreFilesResponse = response.data;

                if (data.success === false) {
                    const errorMessage = (data as any).error?.message || (data as any).error || 'Failed to fetch store files';
                    reject(new Error(errorMessage));
                    return;
                }

                if (data.success && data.data) {
                    if (Array.isArray(data.data.files)) {
                        resolve({
                            files: data.data.files,
                            nextOffset: data.data.next_offset,
                        });
                        return;
                    }
                    if (data.data.files === undefined || data.data.files === null) {
                        resolve({ files: [] });
                        return;
                    }
                }

                reject(new Error('Invalid response from store API: missing files array'));
            })
            .catch(reject);
    });
};

export interface StoreFilesStreamCallbacks {
    onFile: (file: StoreFile) => void;
    onDone: () => void;
    onError: (error: Error) => void;
}

export const getStoreFilesStream = (
    uuid: string,
    callbacks: StoreFilesStreamCallbacks,
    params?: StoreFilesListParams
): void => {
    const query: Record<string, string | number | boolean | undefined> = {};
    if (params?.force_refresh) query.force_refresh = true;
    if (params?.search != null) query.search = params.search;
    if (params?.category != null) query.category = params.category;
    if (params?.game_version != null) query.game_version = params.game_version;
    if (params?.limit != null) query.limit = params.limit;
    if (params?.offset != null) query.offset = params.offset;
    const qs = new URLSearchParams();
    Object.entries(query).forEach(([k, v]) => {
        if (v !== undefined && v !== '') qs.set(k, String(v));
    });
    const url = `/api/client/servers/${uuid}/mcsetups/store/files${qs.toString() ? `?${qs.toString()}` : ''}`;
    const req = new XMLHttpRequest();
    req.open('GET', url);
    req.withCredentials = true;
    req.setRequestHeader('Accept', 'application/json');
    req.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    req.onload = () => {
        if (req.status === 0) {
            callbacks.onError(new Error('Web server is not reachable. Check your connection and try again.'));
            return;
        }
        if (req.status >= 500) {
            callbacks.onError(new Error('Store server is temporarily unavailable. Try again later.'));
            return;
        }
        if (req.status >= 400) {
            callbacks.onError(new Error(req.responseText || `Request failed (${req.status}).`));
            return;
        }
        const text = req.responseText.trim();
        if (!text) {
            callbacks.onDone();
            return;
        }
        const first = text.charAt(0);
        if (first === '[' || first === '{') {
            try {
                const data = JSON.parse(text) as { success?: boolean; data?: { files?: StoreFile[] } };
                const files = data?.data?.files;
                if (Array.isArray(files)) {
                    files.forEach((file, i) => {
                        setTimeout(() => callbacks.onFile(file), i * 50);
                    });
                    setTimeout(() => callbacks.onDone(), files.length * 50 + 20);
                    return;
                }
            } catch {
                callbacks.onError(new Error('Invalid JSON response'));
                return;
            }
        }
        const lines = text.split('\n');
        for (const line of lines) {
            const s = line.trim();
            if (!s) continue;
            try {
                const parsed = JSON.parse(s) as StoreFile | { file?: StoreFile };
                const file = 'file' in parsed ? parsed.file : (parsed as StoreFile);
                if (file && typeof file === 'object' && typeof file.id === 'number') {
                    callbacks.onFile(file as StoreFile);
                }
            } catch {
                //
            }
        }
        callbacks.onDone();
    };
    req.onerror = () => callbacks.onError(new Error('Web server is not reachable. Check your connection and try again.'));
    req.send();
};

export const getStoreFilters = async (uuid: string): Promise<{ game_versions: string[]; categories: string[] }> => {
    return new Promise((resolve, reject) => {
        http.get(`/api/client/servers/${uuid}/mcsetups/store/filters`)
            .then((response) => {
                const data: StoreFiltersResponse = response.data;
                if (data.success && data.data) {
                    resolve({
                        game_versions: Array.isArray(data.data.game_versions) ? data.data.game_versions : [],
                        categories: Array.isArray(data.data.categories) ? data.data.categories : [],
                    });
                } else {
                    resolve({ game_versions: [], categories: [] });
                }
            })
            .catch(() => resolve({ game_versions: [], categories: [] }));
    });
};

export const getStoreFile = async (uuid: string, fileId: number): Promise<StoreFile> => {
    return new Promise((resolve, reject) => {
        http.get(`/api/client/servers/${uuid}/mcsetups/store/files/${fileId}`)
            .then((response) => {
                resolve(response.data.data);
            })
            .catch(reject);
    });
};