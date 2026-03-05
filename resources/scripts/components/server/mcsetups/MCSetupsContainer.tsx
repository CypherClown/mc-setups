import React, { useState, useEffect, memo, useMemo, useRef } from 'react';
import { ServerContext } from '@/state/server';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import { Button } from '@/components/elements/button';
import { Dialog } from '@/components/elements/dialog';
import Alert from '@/components/elements/alert/Alert';
import useFlash from '@/plugins/useFlash';
import { getLicense, MCSetupsLicense } from '@/api/server/mcsetups/mcsetupsLicense';
import { getStoreFilesStream, getStoreFilters, StoreFile, validateLicenseViaBackend, type LicenseValidationResult } from '@/api/server/mcsetups/store';
import { installSetup } from '@/api/server/mcsetups/installation';
import tw from 'twin.macro';
import FlashMessageRender from '@/components/FlashMessageRender';
import Spinner from '@/components/elements/Spinner';
import { LuPackage } from 'react-icons/lu';
import OnboardingModal from './OnboardingModal';
import InstallConfirmationModal from './InstallConfirmationModal';
import SetupSearchBar from './SetupSearchBar';
import SetupCard from './SetupCard';
import SetupModal from './SetupModal';
import styled from 'styled-components/macro';
import deleteFiles from '@/api/server/files/deleteFiles';
import loadDirectory from '@/api/server/files/loadDirectory';

interface ExtendedWindow extends Window {
    SiteConfiguration?: {
        mcsetups?: {
            licenseServerUrl?: string;
            licenseServerKey?: string;
        };
    };
}

const SetupGrid = styled.div`
    ${tw`grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4`};
`;

const SETUP_BATCH_SIZE = 1;
const SETUP_REVEAL_INTERVAL_MS = 50;

const MCSetupsContainer = () => {
    const [license, setLicense] = useState<MCSetupsLicense | null>(null);
    const [storeFiles, setStoreFiles] = useState<StoreFile[]>([]);
    const [searchQuery, setSearchQuery] = useState('');
    const [gameVersion, setGameVersion] = useState('');
    const [category, setCategory] = useState('');
    const [storeFilters, setStoreFilters] = useState<{ game_versions: string[]; categories: string[] } | null>(null);
    const [visibleCount, setVisibleCount] = useState(SETUP_BATCH_SIZE);
    const [loading, setLoading] = useState(true);
    const [loadingStore, setLoadingStore] = useState(false);
    const [saving, setSaving] = useState(false);
    const hasLoadedOnceRef = useRef(false);
    const [showOnboarding, setShowOnboarding] = useState(false);
    const [showInstallConfirm, setShowInstallConfirm] = useState(false);
    const [showSetupModal, setShowSetupModal] = useState(false);
    const [showWipeConfirm, setShowWipeConfirm] = useState(false);
    const [selectedFile, setSelectedFile] = useState<StoreFile | null>(null);
    const [placeholderValues, setPlaceholderValues] = useState<Record<string, string>>({});
    const { clearFlashes, clearAndAddHttpError, addFlash } = useFlash();
    const uuid = ServerContext.useStoreState(state => state.server.data!.uuid);

    const licenseCacheKey = `mcsetups:license:${uuid}`;
    const licenseCacheTimestampKey = `mcsetups:license:${uuid}:timestamp`;
    const validatedLicenseCacheKey = `mcsetups:validated_license:${uuid}`;
    const storeFilesCacheKey = `mcsetups:store_files:${uuid}`;
    const storeFiltersCacheKey = `mcsetups:store_filters:${uuid}`;
    const streamedFilesRef = useRef<StoreFile[]>([]);
    const prevLicenseRef = useRef<string>('');

    const gameVersions = useMemo(() => {
        if (storeFilters?.game_versions?.length) {
            return storeFilters.game_versions.map((value) => ({ value, label: value }));
        }
        const versions = new Set<string>();
        storeFiles.forEach((f) => {
            const v = f.game_version;
            if (v && String(v).trim()) versions.add(String(v).trim());
        });
        return Array.from(versions)
            .sort((a, b) => b.localeCompare(a, undefined, { numeric: true }))
            .map((value) => ({ value, label: value }));
    }, [storeFilters, storeFiles]);

    const categories = useMemo(() => {
        if (storeFilters?.categories?.length) {
            return storeFilters.categories.map((value) => ({ value, label: value }));
        }
        const cats = new Set<string>();
        storeFiles.forEach((f) => {
            const c = f.product_category;
            if (c && String(c).trim()) cats.add(String(c).trim());
        });
        return Array.from(cats).sort().map((value) => ({ value, label: value }));
    }, [storeFilters, storeFiles]);

    const filteredFiles = useMemo(() => {
        let list = storeFiles;
        if (gameVersion) {
            list = list.filter((f) => (f.game_version || '').trim() === gameVersion);
        }
        if (category) {
            list = list.filter((f) => (f.product_category || '').trim() === category);
        }
        if (!searchQuery.trim()) return list;
        const query = searchQuery.toLowerCase();
        return list.filter(
            (file) =>
                file.display_name.toLowerCase().includes(query) ||
                file.description.toLowerCase().includes(query) ||
                file.author_name.toLowerCase().includes(query) ||
                (file.version && file.version.toLowerCase().includes(query))
        );
    }, [storeFiles, searchQuery, gameVersion, category]);

    const validateLicenseInBackground = async (url: string, key: string, forceRefresh: boolean = false): Promise<LicenseValidationResult> => {
        return validateLicenseViaBackend(uuid, url, key, forceRefresh);
    };

    const preValidateLicense = async (skipCache: boolean = false): Promise<{ license: MCSetupsLicense | null; error?: string }> => {
        let lastValidationError: string | undefined;
        try {
            let cachedLicense: MCSetupsLicense | null = null;
            try {
                const cached = localStorage.getItem(validatedLicenseCacheKey);
                if (cached) {
                    const cachedData = JSON.parse(cached);
                    cachedLicense = cachedData.license;
                    if (!skipCache) {
                        const cacheAge = Date.now() - cachedData.timestamp;
                        if (cacheAge < 300000) {
                            return { license: cachedData.license, error: undefined };
                        }
                    }
                }
            } catch (error) {
            }

            let validationResult: LicenseValidationResult;
            try {
                validationResult = await validateLicenseInBackground('', '', skipCache);
            } catch (error) {
                if (cachedLicense) {
                    return { license: cachedLicense, error: undefined };
                }
                return { license: null, error: 'Web server is not reachable. Check your connection and try again.' };
            }

            if (!validationResult.success) {
                lastValidationError = validationResult.error;
            }

            if (!validationResult.success && validationResult.httpStatus === 400 &&
                (validationResult.error?.includes('No license configured') || validationResult.error?.includes('No license'))) {
                if (cachedLicense) {
                    return { license: cachedLicense, error: undefined };
                }
            }

            if (validationResult.success) {
                try {
                    const dbLicense = await getLicense(uuid, skipCache).catch(() => null);
                    if (dbLicense && dbLicense.store_url && dbLicense.license_key) {
                        try {
                            localStorage.setItem(validatedLicenseCacheKey, JSON.stringify({
                                license: dbLicense,
                                timestamp: Date.now(),
                            }));
                            localStorage.setItem(licenseCacheKey, JSON.stringify(dbLicense));
                            localStorage.setItem(licenseCacheTimestampKey, Date.now().toString());
                        } catch (error) {
                        }
                        return { license: dbLicense, error: undefined };
                    }
                } catch (error) {
                }

                const { SiteConfiguration } = window as ExtendedWindow;
                const licenseServerUrl = SiteConfiguration?.mcsetups?.licenseServerUrl || '';
                const licenseServerKey = SiteConfiguration?.mcsetups?.licenseServerKey || '';
                
                if (licenseServerUrl && licenseServerKey) {
                    const license: MCSetupsLicense = {
                        id: 1,
                        license_key: licenseServerKey,
                        store_url: licenseServerUrl,
                        is_active: true,
                        expires_at: null,
                        created_at: new Date().toISOString(),
                        updated_at: new Date().toISOString(),
                    };
                    try {
                        localStorage.setItem(validatedLicenseCacheKey, JSON.stringify({
                            license,
                            timestamp: Date.now(),
                        }));
                    } catch (error) {
                    }
                    return { license, error: undefined };
                }

                const minimalLicense: MCSetupsLicense = {
                    id: 0,
                    license_key: 'backend-managed',
                    store_url: 'https://mcapi.hxdev.org',
                    is_active: true,
                    expires_at: null,
                    created_at: new Date().toISOString(),
                    updated_at: new Date().toISOString(),
                };
                try {
                    localStorage.setItem(validatedLicenseCacheKey, JSON.stringify({
                        license: minimalLicense,
                        timestamp: Date.now(),
                    }));
                } catch (error) {
                }
                return { license: minimalLicense, error: undefined };
            }

            const { SiteConfiguration } = window as ExtendedWindow;
            const licenseServerUrl = SiteConfiguration?.mcsetups?.licenseServerUrl || '';
            const licenseServerKey = SiteConfiguration?.mcsetups?.licenseServerKey || '';

            if (licenseServerUrl && licenseServerKey) {
                try {
                    const validationResult2 = await validateLicenseInBackground(licenseServerUrl, licenseServerKey, skipCache);
                    if (validationResult2.success) {
                        const license: MCSetupsLicense = {
                            id: 1,
                            license_key: licenseServerKey,
                            store_url: licenseServerUrl,
                            is_active: true,
                            expires_at: null,
                            created_at: new Date().toISOString(),
                            updated_at: new Date().toISOString(),
                        };
                        try {
                            localStorage.setItem(validatedLicenseCacheKey, JSON.stringify({
                                license,
                                timestamp: Date.now(),
                            }));
                        } catch (error) {
                        }
                        return { license, error: undefined };
                    }
                } catch (error) {
                }
            }

            try {
                const dbLicense = await getLicense(uuid, skipCache).catch(() => null);
                if (dbLicense && dbLicense.store_url && dbLicense.license_key) {
                    try {
                        const validationResult3 = await validateLicenseInBackground(dbLicense.store_url, dbLicense.license_key, skipCache);
                        if (validationResult3.success) {
                            try {
                                localStorage.setItem(validatedLicenseCacheKey, JSON.stringify({
                                    license: dbLicense,
                                    timestamp: Date.now(),
                                }));
                                localStorage.setItem(licenseCacheKey, JSON.stringify(dbLicense));
                                localStorage.setItem(licenseCacheTimestampKey, Date.now().toString());
                            } catch (error) {
                            }
                            return { license: dbLicense, error: undefined };
                        }
                    } catch (error) {
                    }
                }
            } catch (error) {
            }

            if (cachedLicense) {
                return { license: cachedLicense, error: undefined };
            }

            return { license: null, error: lastValidationError || 'License validation failed.' };
        } catch (error) {
            let fallbackCachedLicense: MCSetupsLicense | null = null;
            try {
                const cached = localStorage.getItem(validatedLicenseCacheKey);
                if (cached) {
                    const cachedData = JSON.parse(cached);
                    fallbackCachedLicense = cachedData.license;
                }
            } catch (e) {
            }
            if (fallbackCachedLicense) {
                return { license: fallbackCachedLicense, error: undefined };
            }
            return { license: null, error: 'Web server is not reachable. Check your connection and try again.' };
        }
    };

    const loadLicense = () => {
        setLoading(true);
        clearFlashes('server:mcsetups');
        let cachedLicense: MCSetupsLicense | null = null;
        try {
            const cached = localStorage.getItem(validatedLicenseCacheKey);
            if (cached) {
                const cachedData = JSON.parse(cached);
                cachedLicense = cachedData.license;
            }
        } catch (error) {
        }

        preValidateLicense(true)
            .then((result) => {
                if (result.license) {
                    setLicense(result.license);
                    clearFlashes('server:mcsetups');
                } else {
                    if (cachedLicense) {
                        setLicense(cachedLicense);
                        clearFlashes('server:mcsetups');
                    } else {
                        setLicense(null);
                        setStoreFiles([]);
                        setVisibleCount(0);
                        try {
                            localStorage.removeItem(storeFilesCacheKey);
                        } catch (error) {
                        }
                        clearFlashes('server:mcsetups');
                        clearAndAddHttpError({
                            key: 'server:mcsetups',
                            error: new Error(result.error || 'License validation failed.'),
                        });
                    }
                }
                setLoading(false);
            })
            .catch((error) => {
                if (cachedLicense) {
                    setLicense(cachedLicense);
                    clearFlashes('server:mcsetups');
                } else {
                    setLicense(null);
                    setStoreFiles([]);
                    setVisibleCount(0);
                    try {
                        localStorage.removeItem(storeFilesCacheKey);
                    } catch (error) {
                    }
                    clearFlashes('server:mcsetups');
                    const message = error?.message || 'Unable to validate license.';
                    clearAndAddHttpError({
                        key: 'server:mcsetups',
                        error: new Error(message.includes('reachable') || message.includes('unavailable') ? message : `License validation failed: ${message}`),
                    });
                }
                setLoading(false);
            });
    };

    useEffect(() => {
        const runBackgroundValidation = async () => {
            await preValidateLicense();
        };
        runBackgroundValidation();
    }, [uuid]);

    useEffect(() => {
        loadLicense();
    }, [uuid]);

    const loadStoreFiles = async (forceRefresh: boolean = false, search?: string) => {
        if (!license?.store_url || !license?.license_key) {
            setStoreFiles([]);
            setLoadingStore(false);
            return;
        }

        if (forceRefresh) {
            try {
                localStorage.removeItem(storeFilesCacheKey);
            } catch (error) {
            }
        }

        setLoadingStore(true);
        setStoreFiles([]);
        setVisibleCount(0);
        streamedFilesRef.current = [];
        const listParams: { search?: string; game_version?: string; category?: string; force_refresh?: boolean } = {};
        if (search?.trim()) listParams.search = search.trim();
        if (gameVersion) listParams.game_version = gameVersion;
        if (category) listParams.category = category;
        if (forceRefresh) listParams.force_refresh = true;
        getStoreFilesStream(
            uuid,
            {
                onFile: (file) => {
                    streamedFilesRef.current = [...streamedFilesRef.current, file];
                    setStoreFiles(streamedFilesRef.current);
                    setVisibleCount((v) => Math.min(v + 1, streamedFilesRef.current.length));
                },
                onDone: () => {
                    setLoadingStore(false);
                    try {
                        const licenseIdentity = license?.store_url && license?.license_key
                            ? `${license.store_url}:${license.license_key}`
                            : '';
                        localStorage.setItem(
                            storeFilesCacheKey,
                            JSON.stringify({
                                files: streamedFilesRef.current,
                                timestamp: Date.now(),
                                licenseIdentity,
                            })
                        );
                    } catch (error) {
                        //
                    }
                },
                onError: (err) => {
                    setStoreFiles([]);
                    setVisibleCount(0);
                    setLoadingStore(false);
                    clearAndAddHttpError({
                        key: 'server:mcsetups',
                        error: err instanceof Error ? err : new Error('Failed to load setups.'),
                    });
                },
            },
            Object.keys(listParams).length ? listParams : undefined
        );
    };

    useEffect(() => {
        if (license && license.is_active && license.store_url && license.license_key) {
            const licenseIdentity = `${license.store_url}:${license.license_key}`;
            if (prevLicenseRef.current && prevLicenseRef.current !== licenseIdentity) {
                try {
                    localStorage.removeItem(storeFilesCacheKey);
                    localStorage.removeItem(storeFiltersCacheKey);
                } catch {
                    //
                }
            }
            prevLicenseRef.current = licenseIdentity;

            const cachedFilters = localStorage.getItem(storeFiltersCacheKey);
            if (cachedFilters) {
                try {
                    const { data, timestamp } = JSON.parse(cachedFilters);
                    if (Date.now() - timestamp < 60000) {
                        setStoreFilters(data);
                    }
                } catch (error) {
                    //
                }
            }
            getStoreFilters(uuid).then((f) => {
                setStoreFilters(f);
                try {
                    localStorage.setItem(storeFiltersCacheKey, JSON.stringify({ data: f, timestamp: Date.now() }));
                } catch (error) {
                    //
                }
            });
            const cached = localStorage.getItem(storeFilesCacheKey);
            if (cached) {
                try {
                    const cachedData = JSON.parse(cached);
                    const cacheLicenseMatches = cachedData.licenseIdentity === licenseIdentity;
                    const cacheAge = Date.now() - (cachedData.timestamp || 0);
                    if (cacheLicenseMatches && cacheAge < 300000) {
                        setStoreFiles(cachedData.files || []);
                        setVisibleCount(v => Math.min(SETUP_BATCH_SIZE, (cachedData.files || []).length));
                        setLoadingStore(false);
                        hasLoadedOnceRef.current = true;
                    } else {
                        if (!hasLoadedOnceRef.current) {
                            loadStoreFiles(false);
                            hasLoadedOnceRef.current = true;
                        } else {
                            setStoreFiles([]);
                            setVisibleCount(0);
                            setLoadingStore(false);
                        }
                    }
                } catch (error) {
                    if (!hasLoadedOnceRef.current) {
                        loadStoreFiles(false);
                        hasLoadedOnceRef.current = true;
                    } else {
                        setStoreFiles([]);
                        setVisibleCount(0);
                        setLoadingStore(false);
                    }
                }
            } else {
                if (!hasLoadedOnceRef.current) {
                    loadStoreFiles(false);
                    hasLoadedOnceRef.current = true;
                } else {
                    setStoreFiles([]);
                    setVisibleCount(0);
                    setLoadingStore(false);
                }
            }
        } else {
            prevLicenseRef.current = '';
            setStoreFiles([]);
            setStoreFilters(null);
            setVisibleCount(0);
            setLoadingStore(false);
            hasLoadedOnceRef.current = false;
            try {
                localStorage.removeItem(storeFilesCacheKey);
                localStorage.removeItem(storeFiltersCacheKey);
            } catch (error) {
            }
        }
    }, [license]);

    useEffect(() => {
        const hasOpenModal = showOnboarding || showInstallConfirm || showSetupModal || showWipeConfirm;
        
        if (!hasOpenModal) {
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
        }
        
        return () => {
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
        };
    }, [showOnboarding, showInstallConfirm, showSetupModal, showWipeConfirm]);

    useEffect(() => {
        return () => {
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
        };
    }, []);

    useEffect(() => {
        setVisibleCount(v => Math.min(SETUP_BATCH_SIZE, filteredFiles.length));
    }, [filteredFiles.length, searchQuery, gameVersion, category]);

    useEffect(() => {
        if (visibleCount >= filteredFiles.length) return;
        const t = setTimeout(() => {
            setVisibleCount(prev => Math.min(prev + SETUP_BATCH_SIZE, filteredFiles.length));
        }, SETUP_REVEAL_INTERVAL_MS);
        return () => clearTimeout(t);
    }, [visibleCount, filteredFiles.length]);

    const handleViewSetup = (file: StoreFile) => {
        setSelectedFile(file);
        setShowSetupModal(true);
    };

    const handleInstall = (file: StoreFile) => {
        if (!license?.license_key || !license?.store_url) {
            addFlash({
                key: 'server:mcsetups',
                type: 'error',
                message: 'License key is required to install setups.',
            });
            return;
        }

        setShowSetupModal(false);

        if (file.placeholders.length > 0) {
            setSelectedFile(file);
            setShowOnboarding(true);
        } else {
            setSelectedFile(file);
            setPlaceholderValues({});
            setShowWipeConfirm(true);
        }
    };

    const handleOnboardingComplete = (values: Record<string, string>) => {
        setPlaceholderValues(values);
        setShowOnboarding(false);
        setShowWipeConfirm(true);
    };

    const handleWipeConfirm = async () => {
        if (!selectedFile || !license?.license_key || !license?.store_url) return;

        setSaving(true);
        setShowWipeConfirm(false);
        clearFlashes('server:mcsetups');

        try {
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
            }

            const addonIds = selectedFile.addons.map(addon => addon.id);
            await installSetup(uuid, {
                file_id: selectedFile.id,
                placeholder_values: placeholderValues,
                addon_ids: addonIds,
                wipe_data: false,
                zip_and_wipe: false,
            });

            addFlash({
                key: 'server:mcsetups',
                type: 'success',
                message: 'Setup installation completed. You can start your server from the Console.',
            });

            setSelectedFile(null);
            setPlaceholderValues({});
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
        } catch (error) {
            clearAndAddHttpError({ key: 'server:mcsetups', error });
        } finally {
            setSaving(false);
        }
    };

    const handleInstallConfirm = async (wipeData: boolean, zipAndWipe: boolean) => {
        if (!selectedFile || !license?.license_key || !license?.store_url) return;

        setSaving(true);
        clearFlashes('server:mcsetups');

        try {
            const addonIds = selectedFile.addons.map(addon => addon.id);
            await installSetup(uuid, {
                file_id: selectedFile.id,
                placeholder_values: placeholderValues,
                addon_ids: addonIds,
                wipe_data: false,
                zip_and_wipe: false,
            });

            addFlash({
                key: 'server:mcsetups',
                type: 'success',
                message: 'Setup installation started. Open the Console tab to see live progress (Step 1/6, download, extract, etc.).',
            });

            setShowInstallConfirm(false);
            setSelectedFile(null);
            setPlaceholderValues({});
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
        } catch (error) {
            clearAndAddHttpError({ key: 'server:mcsetups', error });
        } finally {
            setSaving(false);
        }
    };

    if (loading) {
        return (
            <ServerContentBlock title={'MCSetups'}>
                <div css={tw`flex items-center justify-center py-8`}>
                    <Spinner size={'large'} />
                </div>
            </ServerContentBlock>
        );
    }

    const hasValidLicense = license && license.is_active && license.store_url && license.license_key;

    return (
        <ServerContentBlock 
            showFlashKey={'server:mcsetups'} 
            title={'MCSetups'}
        >
            <FlashMessageRender byKey={'server:mcsetups'} css={tw`mb-4`} />

            <div css={tw`space-y-6`} style={{ minHeight: 0 }}>
                {hasValidLicense && (
                    <div>
                        {loadingStore ? (
                            <div css={tw`flex items-center justify-center py-8`}>
                                <Spinner size={'large'} />
                            </div>
                        ) : storeFiles.length === 0 ? (
                            <div css={tw`bg-neutral-800 rounded-lg p-6 border border-neutral-700 text-center text-neutral-400`}>
                                No setups available
                            </div>
                        ) : (
                            <>
                                <SetupSearchBar
                                    searchQuery={searchQuery}
                                    onSearchChange={setSearchQuery}
                                    gameVersion={gameVersion}
                                    onGameVersionChange={setGameVersion}
                                    gameVersions={gameVersions}
                                    category={category}
                                    onCategoryChange={setCategory}
                                    categories={categories}
                                    onRefresh={() => loadStoreFiles(true, searchQuery)}
                                    refreshing={loadingStore}
                                />
                                {filteredFiles.length === 0 ? (
                                    <div css={tw`bg-neutral-800 rounded-lg p-6 border border-neutral-700 text-center text-neutral-400`}>
                                        No setups found matching "{searchQuery}"
                                    </div>
                                ) : (
                                    <SetupGrid>
                                        {filteredFiles.slice(0, visibleCount).map((file) => (
                                            <SetupCard
                                                key={file.id}
                                                file={file}
                                                storeUrl={license?.store_url}
                                                onInstall={handleInstall}
                                                onViewDetails={handleViewSetup}
                                            />
                                        ))}
                                    </SetupGrid>
                                )}
                            </>
                        )}
                    </div>
                )}

                {!hasValidLicense && (
                    <div css={tw`bg-neutral-800 rounded-lg p-6 border border-neutral-700 text-center`}>
                        <p css={tw`text-neutral-400 mb-2`}>
                            No license key configured.
                        </p>
                    </div>
                )}
            </div>

            {selectedFile && (
                <>
                    <SetupModal
                        open={showSetupModal}
                        onClose={() => {
                            setShowSetupModal(false);
                            setSelectedFile(null);
                        }}
                        file={selectedFile}
                        onInstall={() => handleInstall(selectedFile)}
                    />
                    <OnboardingModal
                        open={showOnboarding}
                        onClose={() => {
                            setShowOnboarding(false);
                            setSelectedFile(null);
                        }}
                        file={selectedFile}
                        onComplete={handleOnboardingComplete}
                    />
                    <InstallConfirmationModal
                        open={showInstallConfirm}
                        file={selectedFile}
                        onClose={() => {
                            setShowInstallConfirm(false);
                            setSelectedFile(null);
                            setPlaceholderValues({});
                        }}
                        onConfirm={handleInstallConfirm}
                    />
                    <Dialog.Confirm
                        open={showWipeConfirm}
                        title="Confirm Setup Installation"
                        confirm="Install Setup"
                        onClose={() => {
                            setShowWipeConfirm(false);
                            setSelectedFile(null);
                            setPlaceholderValues({});
                        }}
                        onConfirmed={handleWipeConfirm}
                    >
                        <div css={tw`space-y-4`}>
                            <p css={tw`text-sm`}>
                                You are about to install <strong>{selectedFile?.display_name}</strong> on this server.
                            </p>

                            {selectedFile && (
                                <div css={tw`bg-neutral-700 rounded p-3`}>
                                    <div>
                                        <span>Setup: </span>
                                        <strong>{selectedFile.display_name}</strong>
                                    </div>
                                    {selectedFile.author_name && (
                                        <div css={tw`mt-2`}>
                                            <span>Author: </span>
                                            <strong>{selectedFile.author_name}</strong>
                                        </div>
                                    )}
                                    {selectedFile.version && (
                                        <div css={tw`mt-2`}>
                                            <span>Version: </span>
                                            <strong>{selectedFile.version}</strong>
                                        </div>
                                    )}
                                </div>
                            )}

                            <Alert type="danger" className="mt-4">
                                Warning: This will delete ALL existing files on your server. All current server files will be permanently removed before installing the new setup. This action cannot be undone.
                            </Alert>
                        </div>
                    </Dialog.Confirm>
                </>
            )}
        </ServerContentBlock>
    );
};

export default memo(MCSetupsContainer);
