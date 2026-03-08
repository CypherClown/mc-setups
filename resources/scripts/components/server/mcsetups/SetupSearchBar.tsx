import React from 'react';
import tw from 'twin.macro';
import styled from 'styled-components/macro';
import { LuSearch, LuRefreshCw, LuServer, LuPackage } from 'react-icons/lu';
import Select from '@/components/elements/Select';
import Input from '@/components/elements/Input';
import Spinner from '@/components/elements/Spinner';

const FilterContainer = styled.div`
    ${tw`flex gap-2 sm:gap-4 mb-4 items-stretch sm:items-center flex-col sm:flex-row flex-wrap`};
`;

const FilterGroup = styled.div`
    ${tw`relative flex items-center w-full sm:w-auto min-w-0`};
    &.filter-version { min-width: 140px; }
    &.filter-category { min-width: 160px; }
`;

const IconWrapper = styled.div`
    ${tw`absolute left-3 pointer-events-none z-10 text-neutral-400`};
`;

const SearchIconWrapper = styled.div`
    ${tw`absolute left-3 pointer-events-none z-10 text-neutral-400`};
    top: 10px;
`;

const RefreshIconWrapper = styled.button`
    ${tw`absolute right-3 pointer-events-auto z-10 text-neutral-400 hover:text-neutral-200 transition-colors cursor-pointer`};
    ${tw`focus:outline-none`};
    top: 10px;
`;

const StyledSelect = styled(Select)<{ hasIcon?: boolean }>`
    ${tw`w-full h-10`};
    ${props => props.hasIcon && tw`pl-10`};
    padding-top: 9px;
    & > option {
        padding: 0.25rem 0.5rem;
        white-space: normal;
        word-wrap: break-word;
    }
`;

const StyledInput = styled(Input)`
    ${tw`w-full h-10`};
    padding-left: 38px !important;
    padding-right: 40px !important;
    &::placeholder {
        ${tw`text-neutral-400`};
    }
`;

interface Props {
    searchQuery: string;
    onSearchChange: (query: string) => void;
    gameVersion: string;
    onGameVersionChange: (value: string) => void;
    gameVersions: Array<{ value: string; label: string }>;
    category: string;
    onCategoryChange: (value: string) => void;
    categories: Array<{ value: string; label: string }>;
    onRefresh?: () => void;
    refreshing?: boolean;
}

const SetupSearchBar = ({
    searchQuery,
    onSearchChange,
    gameVersion,
    onGameVersionChange,
    gameVersions,
    category,
    onCategoryChange,
    categories,
    onRefresh,
    refreshing = false,
}: Props) => {
    return (
        <FilterContainer>
            <FilterGroup className="filter-version" css={tw`sm:min-w-[140px]`}>
                <IconWrapper>
                    <LuServer size={18} strokeWidth={2.5} />
                </IconWrapper>
                <StyledSelect
                    hasIcon
                    value={gameVersion}
                    onChange={(e) => onGameVersionChange(e.target.value)}
                >
                    <option value="">All Versions</option>
                    {gameVersions.map((v) => (
                        <option key={v.value} value={v.value}>
                            {v.label}
                        </option>
                    ))}
                </StyledSelect>
            </FilterGroup>
            <FilterGroup className="filter-category" css={tw`sm:min-w-[160px]`}>
                <IconWrapper>
                    <LuPackage size={18} strokeWidth={2.5} />
                </IconWrapper>
                <StyledSelect
                    hasIcon
                    value={category}
                    onChange={(e) => onCategoryChange(e.target.value)}
                >
                    <option value="">All Categories</option>
                    {categories.map((c) => (
                        <option key={c.value} value={c.value}>
                            {c.label}
                        </option>
                    ))}
                </StyledSelect>
            </FilterGroup>
            <FilterGroup css={tw`flex-1`}>
                <div css={tw`relative w-full`}>
                    <SearchIconWrapper>
                        <LuSearch size={18} strokeWidth={2.5} />
                    </SearchIconWrapper>
                    <StyledInput
                        type="text"
                        value={searchQuery}
                        onChange={(e) => onSearchChange(e.target.value)}
                        placeholder="Search setups..."
                    />
                    {onRefresh && (
                        <RefreshIconWrapper
                            onClick={onRefresh}
                            disabled={refreshing}
                            title="Refresh"
                        >
                            {refreshing ? (
                                <Spinner size={'small'} />
                            ) : (
                                <LuRefreshCw size={18} strokeWidth={2.5} />
                            )}
                        </RefreshIconWrapper>
                    )}
                </div>
            </FilterGroup>
        </FilterContainer>
    );
};

export default SetupSearchBar;
