@extends('layouts.admin')

@section('title')
    MCSetups License Management
@endsection

@section('content-header')
    <h1>MCSetups License Management<small>Manage global license key for MCSetups addon</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">MCSetups</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Global License</h3>
            </div>
            <div class="box-body">
                <form action="{{ $license ? route('admin.mcsetups.update') : route('admin.mcsetups.create') }}" method="POST">
                    @csrf
                    @method('POST')
                    <div class="form-group">
                        <label for="store_url">WebServer URL</label>
                        <input 
                            type="text" 
                            class="form-control" 
                            id="store_url" 
                            name="store_url" 
                            value="{{ $license ? $license->store_url : old('store_url') }}" 
                            placeholder="https://mcapi.hxdev.org or mcapi.hxdev.org"
                            required
                        >
                        <small class="help-block">Enter the base URL.</small>
                    </div>
                    <div class="form-group">
                        <label for="license_key">License Key</label>
                        <input 
                            type="text" 
                            class="form-control" 
                            id="license_key" 
                            name="license_key" 
                            value="{{ $license ? $license->license_key : old('license_key') }}" 
                            required
                        >
                    </div>
                    <hr>
                    <h4>S3 Storage (for upload add-ons)</h4>
                    <p class="text-muted">Configure S3/MinIO to upload and store your own add-ons. Leave blank to skip.</p>
                    <div class="form-group">
                        <label for="s3_endpoint">S3 Endpoint</label>
                        <input 
                            type="text" 
                            class="form-control" 
                            id="s3_endpoint" 
                            name="s3_endpoint" 
                            value="{{ $license ? $license->s3_endpoint : old('s3_endpoint') }}" 
                            placeholder="http://46.202.166.42:19000 or https://s3.amazonaws.com"
                        >
                    </div>
                    <div class="form-group">
                        <label for="s3_access_key">S3 Access Key</label>
                        <input 
                            type="text" 
                            class="form-control" 
                            id="s3_access_key" 
                            name="s3_access_key" 
                            value="{{ $license ? $license->s3_access_key : old('s3_access_key') }}" 
                            placeholder="{{ $license && $license->s3_access_key ? 'Leave blank to keep current' : 'minioadmin' }}"
                            autocomplete="off"
                        >
                    </div>
                    <div class="form-group">
                        <label for="s3_secret_key">S3 Secret Key</label>
                        <input 
                            type="password" 
                            class="form-control" 
                            id="s3_secret_key" 
                            name="s3_secret_key" 
                            value="" 
                            placeholder="{{ $license && $license->s3_access_key ? 'Leave blank to keep current' : '••••••••' }}"
                            autocomplete="off"
                        >
                        <small class="help-block">Hidden like a password. Leave blank to keep current.</small>
                    </div>
                    <div class="form-group">
                        <label for="s3_bucket">S3 Bucket</label>
                        <input 
                            type="text" 
                            class="form-control" 
                            id="s3_bucket" 
                            name="s3_bucket" 
                            value="{{ $license ? $license->s3_bucket : old('s3_bucket') }}" 
                            placeholder="mcsetups-addons"
                        >
                    </div>
                    <div class="form-group">
                        <label for="s3_region">S3 Region</label>
                        <input 
                            type="text" 
                            class="form-control" 
                            id="s3_region" 
                            name="s3_region" 
                            value="{{ $license ? $license->s3_region : old('s3_region') }}" 
                            placeholder="us-east-1 (optional for MinIO)"
                        >
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-save"></i> {{ $license ? 'Update License' : 'Save License' }}
                        </button>
                        @if($license)
                            <button type="button" class="btn btn-danger" style="margin-left: 10px;" onclick="deleteLicense()">
                                <i class="fa fa-trash"></i> Delete
                            </button>
                        @endif
                    </div>
                </form>
                @if($license)
                    <form id="deleteForm" action="{{ route('admin.mcsetups.delete') }}" method="POST" style="display: none;">
                        @csrf
                        @method('DELETE')
                    </form>
                    <script>
                        function deleteLicense() {
                            if (confirm('Are you sure you want to delete this license?')) {
                                document.getElementById('deleteForm').submit();
                            }
                        }
                    </script>
                @endif
            </div>
        </div>
    </div>
</div>

@if($license && $license->hasS3Config())
<style>
/* Dark theme for Products/Addons tabs */
.mcsetups-upload-box .nav-tabs {
    border-bottom-color: rgba(255,255,255,0.15);
}
.mcsetups-upload-box .nav-tabs > li > a {
    color: rgba(255,255,255,0.8);
    border-color: transparent;
    background: transparent !important;
}
.mcsetups-upload-box .nav-tabs > li > a:hover {
    background: rgba(0,0,0,0.15) !important;
    border-color: rgba(255,255,255,0.2);
    color: #e8e8e8;
}
.mcsetups-upload-box .nav-tabs > li.active > a,
.mcsetups-upload-box .nav-tabs > li.active > a:hover,
.mcsetups-upload-box .nav-tabs > li.active > a:focus {
    background: rgba(0,0,0,0.25) !important;
    border-color: rgba(255,255,255,0.15);
    border-bottom-color: transparent;
    color: #e8e8e8;
}
.mcsetups-upload-box .nav-tabs .badge {
    background: rgba(255,255,255,0.2);
    color: #e8e8e8;
}
.mcsetups-upload-box .tab-content {
    padding-top: 1rem;
}
.mcsetups-upload-box .tab-pane {
    color: #e8e8e8;
}
.mcsetups-upload-box .tab-pane .text-muted {
    color: rgba(255,255,255,0.6) !important;
}
.mcsetups-upload-box .tab-pane .table {
    background: rgba(0,0,0,0.15);
    color: #e8e8e8;
}
.mcsetups-upload-box .tab-pane .table th,
.mcsetups-upload-box .tab-pane .table td {
    border-color: rgba(255,255,255,0.15);
}
.mcsetups-upload-box .tab-pane .table-striped > tbody > tr:nth-of-type(odd) {
    background: rgba(0,0,0,0.1);
}
.mcsetups-upload-box .tab-pane .table a {
    color: #7eb8ff;
}
</style>
<div class="row">
    <div class="col-xs-12">
        <div class="box mcsetups-upload-box">
            <div class="box-header with-border">
                <h3 class="box-title">Uploads: Setups &amp; Add-ons</h3>
            </div>
            <div class="box-body">
                <p class="text-muted"><strong>Products</strong> = full server setup archives (zip) — what users install as a setup. <strong>Add-ons</strong> = plugins, resource packs, mods — optional files that can be bundled with a product. Stored in your S3 bucket.</p>
                <ul class="nav nav-tabs" role="tablist">
                    <li role="presentation" class="active">
                        <a href="#tab-products" aria-controls="tab-products" role="tab" data-toggle="tab">
                            Products (server setups) <span id="products-count-badge" class="badge">0</span>
                        </a>
                    </li>
                    <li role="presentation">
                        <a href="#tab-addons" aria-controls="tab-addons" role="tab" data-toggle="tab">
                            Add-ons (plugins, packs, mods) <span id="addons-count-badge" class="badge">0</span>
                        </a>
                    </li>
                </ul>
                <div class="tab-content">
                    <div role="tabpanel" class="tab-pane active" id="tab-products">
                        <button type="button" class="btn btn-primary btn-sm" id="add-product-btn"><i class="fa fa-plus"></i> Add product (setup)</button>
                        <div id="uploaded-products-list" style="margin-top: 1rem;">
                            <p class="text-muted"><i class="fa fa-spinner fa-spin"></i> Loading...</p>
                        </div>
                    </div>
                    <div role="tabpanel" class="tab-pane" id="tab-addons">
                        <button type="button" class="btn btn-primary btn-sm" id="add-addon-btn"><i class="fa fa-plus"></i> Add add-on (plugin/pack)</button>
                        <div id="uploaded-addons-list" style="margin-top: 1rem;">
                            <p class="text-muted">Switch to this tab to load add-ons.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Add Product/Addon Modal (unified, dark styling) --}}
<style>
#add-product-modal .modal-content input,
#add-product-modal .modal-content textarea,
#add-product-modal .modal-content select {
    background: rgba(0,0,0,0.25) !important;
    border-color: rgba(255,255,255,0.15) !important;
    color: #e8e8e8 !important;
}
#add-product-modal .modal-content input::placeholder,
#add-product-modal .modal-content textarea::placeholder {
    color: rgba(255,255,255,0.5) !important;
}
#add-product-modal #add-product-required-addons,
#add-product-modal #add-product-optional-addons {
    background: rgba(0,0,0,0.25) !important;
    border-color: rgba(255,255,255,0.15) !important;
    color: #e8e8e8 !important;
}
#add-product-modal .ph-row {
    background: rgba(0,0,0,0.2) !important;
    border-color: rgba(255,255,255,0.15) !important;
}
#add-product-modal .btn-default:not(.active) {
    background: rgba(0,0,0,0.25) !important;
    border-color: rgba(255,255,255,0.15) !important;
    color: #e8e8e8 !important;
}
</style>
<div id="add-product-modal" class="modal fade" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title" id="add-modal-title">Add Product</h4>
            </div>
            <div class="modal-body">
                <div class="btn-group btn-group-sm margin-bottom">
                    <button type="button" class="btn btn-default active" id="modal-mode-product">Product</button>
                    <button type="button" class="btn btn-default" id="modal-mode-addon">Addon</button>
                </div>
                <form id="add-product-form">
                    <div id="modal-product-fields">
                        <div class="form-group">
                            <label for="add-product-file">Archive File *</label>
                            <input type="file" class="form-control" id="add-product-file" accept=".zip,.rar,.7z,.tar,.gz">
                            <small class="help-block">zip, rar, 7z, tar, gz</small>
                        </div>
                    </div>
                        <div id="modal-addon-fields" class="hide">
                        <div class="form-group">
                            <label for="add-addon-file">File *</label>
                            <input type="file" class="form-control" id="add-addon-file" accept=".zip,.mcaddon,.mcpack,.mctemplate">
                            <small class="help-block">zip, mcaddon, mcpack, mctemplate</small>
                        </div>
                        <div class="row">
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label for="add-addon-file-type">Type (optional)</label>
                                    <input type="text" class="form-control" id="add-addon-file-type" placeholder="e.g. Plugin, Mod, Resource Pack">
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label for="add-addon-file-location">File Location (optional)</label>
                                    <input type="text" class="form-control" id="add-addon-file-location" placeholder="e.g. /plugins, /mods">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="add-product-display-name">Name *</label>
                        <input type="text" class="form-control" id="add-product-display-name" required placeholder="Product or addon name">
                    </div>
                    <div class="form-group" id="modal-description-wrap">
                        <label for="add-product-description">Description (optional)</label>
                        <textarea class="form-control" id="add-product-description" rows="3" placeholder="Product description"></textarea>
                    </div>
                    <div class="form-group" id="modal-author-wrap">
                        <label for="add-product-author-name">Author name (optional)</label>
                        <input type="text" class="form-control" id="add-product-author-name" placeholder="e.g. Your name or team">
                    </div>
                    <div class="form-group">
                        <label>Placeholders (optional)</label>
                        <p class="text-muted help-block">Define placeholders. Users will see these when downloading.</p>
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-default active" id="add-product-ph-mode-manual">Manual Edit</button>
                            <button type="button" class="btn btn-default" id="add-product-ph-mode-json">JSON Edit</button>
                        </div>
                        <div id="add-product-placeholders-manual-wrap" style="margin-top: 0.5rem;">
                            <div id="add-product-placeholders-manual-list"></div>
                            <button type="button" class="btn btn-link btn-sm" id="add-product-placeholder-add">+ Add placeholder</button>
                        </div>
                        <div id="add-product-placeholders-json-wrap" class="hide" style="margin-top: 0.5rem;">
                            <textarea id="add-product-placeholders-json" class="form-control" rows="8" placeholder='[{"token":"%%__SERVER_NAME__%%","label":"Server Name","description":"Your Minecraft server name","example":"My Awesome Server"}]' style="font-family: monospace; font-size: 12px;"></textarea>
                            <p id="add-product-placeholders-json-error" class="text-danger" style="font-size: 12px; display: none;"></p>
                        </div>
                    </div>
                    <div class="form-group" id="modal-product-addons-wrap">
                        <label>Required addons (optional)</label>
                        <p class="text-muted help-block">Addons that are required for the product.</p>
                        <div id="add-product-required-addons" style="max-height: 120px; overflow-y: auto; border: 1px solid rgba(255,255,255,0.15); border-radius: 4px; padding: 8px; background: rgba(0,0,0,0.25);"></div>
                    </div>
                    <div class="form-group" id="modal-product-addons-opt-wrap">
                        <label>Optional addons (optional)</label>
                        <p class="text-muted help-block">Addons that users can optionally include.</p>
                        <div id="add-product-optional-addons" style="max-height: 120px; overflow-y: auto; border: 1px solid rgba(255,255,255,0.15); border-radius: 4px; padding: 8px; background: rgba(0,0,0,0.25);"></div>
                    </div>
                    <div class="form-group" id="modal-game-version-wrap">
                        <label for="add-product-game-version">Game version (optional)</label>
                        <input type="text" class="form-control" id="add-product-game-version" placeholder="e.g. 1.20.1">
                    </div>
                    <div class="form-group">
                        <label for="add-product-category">Category (optional)</label>
                        <input type="text" class="form-control" id="add-product-category" placeholder="e.g. Survival, Resource Pack, Config">
                    </div>
                    <div class="form-group">
                        <label for="add-product-cover-image">Cover image (optional)</label>
                        <input type="file" class="form-control" id="add-product-cover-image" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
                        <small class="help-block">JPG, PNG, GIF or WebP, max 5 MB. Uploaded to S3.</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="add-product-submit"><i class="fa fa-upload"></i> Upload</button>
            </div>
        </div>
    </div>
</div>

{{-- Edit Product modal (meta only) --}}
<div id="edit-product-modal" class="modal fade" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Edit Product</h4>
            </div>
            <div class="modal-body">
                <input type="hidden" id="edit-product-path" value="">
                <input type="hidden" id="edit-product-id" value="">
                <div class="form-group">
                    <label for="edit-product-display-name">Name *</label>
                    <input type="text" class="form-control" id="edit-product-display-name" required>
                </div>
                <div class="form-group">
                    <label for="edit-product-description">Description (optional)</label>
                    <textarea class="form-control" id="edit-product-description" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label for="edit-product-author-name">Author name (optional)</label>
                    <input type="text" class="form-control" id="edit-product-author-name">
                </div>
                <div class="form-group">
                    <label for="edit-product-product-link">Product link (optional)</label>
                    <input type="url" class="form-control" id="edit-product-product-link" placeholder="https://...">
                </div>
                <div class="form-group">
                    <label>Cover image (optional)</label>
                    <input type="hidden" id="edit-product-cover-image-url" value="">
                    <div id="edit-product-cover-current" class="margin-bottom" style="display:none;"><img id="edit-product-cover-img" src="" alt="Cover" style="max-height:120px; max-width:200px; border:1px solid rgba(255,255,255,0.2); border-radius:4px;"></div>
                    <input type="file" class="form-control" id="edit-product-cover-image-file" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
                    <small class="help-block">Upload a new image to replace. JPG, PNG, GIF or WebP, max 5 MB.</small>
                </div>
                <div class="form-group">
                    <label for="edit-product-source-name">Source name / License product (optional)</label>
                    <input type="text" class="form-control" id="edit-product-source-name" placeholder="e.g. MC-STORE">
                </div>
                <div class="form-group">
                    <label for="edit-product-price">Price (optional, leave empty for free)</label>
                    <input type="number" class="form-control" id="edit-product-price" min="0" step="0.01" placeholder="0.00">
                </div>
                <div class="form-group">
                    <label>Placeholders (optional)</label>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-default active" id="edit-product-ph-mode-manual">Manual</button>
                        <button type="button" class="btn btn-default" id="edit-product-ph-mode-json">JSON</button>
                    </div>
                    <div id="edit-product-placeholders-manual-wrap" style="margin-top:0.5rem;">
                        <div id="edit-product-placeholders-manual-list"></div>
                        <button type="button" class="btn btn-link btn-sm" id="edit-product-placeholder-add">+ Add placeholder</button>
                    </div>
                    <div id="edit-product-placeholders-json-wrap" class="hide" style="margin-top:0.5rem;">
                        <textarea id="edit-product-placeholders-json" class="form-control" rows="6" style="font-family:monospace;font-size:12px;"></textarea>
                    </div>
                </div>
                <div class="form-group">
                    <label>Required addons (optional)</label>
                    <div id="edit-product-required-addons" style="max-height:100px;overflow-y:auto;border:1px solid rgba(255,255,255,0.15);border-radius:4px;padding:8px;"></div>
                </div>
                <div class="form-group">
                    <label>Optional addons (optional)</label>
                    <div id="edit-product-optional-addons" style="max-height:100px;overflow-y:auto;border:1px solid rgba(255,255,255,0.15);border-radius:4px;padding:8px;"></div>
                </div>
                <div class="form-group">
                    <label for="edit-product-game-version">Game version (optional)</label>
                    <input type="text" class="form-control" id="edit-product-game-version">
                </div>
                <div class="form-group">
                    <label for="edit-product-category">Category (optional)</label>
                    <input type="text" class="form-control" id="edit-product-category">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="edit-product-submit"><i class="fa fa-save"></i> Save</button>
            </div>
        </div>
    </div>
</div>

{{-- Update Product Version modal (new file + meta) --}}
<div id="update-product-version-modal" class="modal fade" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Upload New Version (Product)</h4>
            </div>
            <div class="modal-body">
                <input type="hidden" id="update-product-version-path" value="">
                <div class="form-group">
                    <label for="update-product-version-file">New archive file *</label>
                    <input type="file" class="form-control" id="update-product-version-file" accept=".zip,.rar,.7z,.tar,.gz">
                </div>
                <div class="form-group">
                    <label for="update-product-version-display-name">Name *</label>
                    <input type="text" class="form-control" id="update-product-version-display-name" required>
                </div>
                <div class="form-group">
                    <label for="update-product-version-description">Description (optional)</label>
                    <textarea class="form-control" id="update-product-version-description" rows="2"></textarea>
                </div>
                <div class="form-group">
                    <label for="update-product-version-author-name">Author (optional)</label>
                    <input type="text" class="form-control" id="update-product-version-author-name">
                </div>
                <div class="form-group">
                    <label for="update-product-version-game-version">Game version (optional)</label>
                    <input type="text" class="form-control" id="update-product-version-game-version">
                </div>
                <div class="form-group">
                    <label for="update-product-version-category">Category (optional)</label>
                    <input type="text" class="form-control" id="update-product-version-category">
                </div>
                <div class="form-group">
                    <label for="update-product-version-product-link">Product link (optional)</label>
                    <input type="url" class="form-control" id="update-product-version-product-link" placeholder="https://...">
                </div>
                <div class="form-group">
                    <label>Cover image (optional)</label>
                    <input type="hidden" id="update-product-version-cover-image-url" value="">
                    <div id="update-product-version-cover-current" class="margin-bottom" style="display:none;"><img id="update-product-version-cover-img" src="" alt="Cover" style="max-height:120px; max-width:200px; border:1px solid rgba(255,255,255,0.2); border-radius:4px;"></div>
                    <input type="file" class="form-control" id="update-product-version-cover-image-file" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
                    <small class="help-block">Upload a new image. JPG, PNG, GIF or WebP, max 5 MB.</small>
                </div>
                <div class="form-group">
                    <label for="update-product-version-source-name">Source name (optional)</label>
                    <input type="text" class="form-control" id="update-product-version-source-name" placeholder="e.g. MC-STORE">
                </div>
                <div class="form-group">
                    <label for="update-product-version-price">Price (optional)</label>
                    <input type="number" class="form-control" id="update-product-version-price" min="0" step="0.01" placeholder="0.00">
                </div>
                <div class="form-group">
                    <label>Required addons (optional)</label>
                    <div id="update-product-version-required-addons" style="max-height:80px;overflow-y:auto;border:1px solid rgba(255,255,255,0.15);border-radius:4px;padding:8px;"></div>
                </div>
                <div class="form-group">
                    <label>Optional addons (optional)</label>
                    <div id="update-product-version-optional-addons" style="max-height:80px;overflow-y:auto;border:1px solid rgba(255,255,255,0.15);border-radius:4px;padding:8px;"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="update-product-version-submit"><i class="fa fa-upload"></i> Update version</button>
            </div>
        </div>
    </div>
</div>

{{-- Edit Add-on modal --}}
<div id="edit-addon-modal" class="modal fade" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Edit Add-on</h4>
            </div>
            <div class="modal-body">
                <input type="hidden" id="edit-addon-path" value="">
                <input type="hidden" id="edit-addon-id" value="">
                <div class="form-group">
                    <label for="edit-addon-display-name">Name *</label>
                    <input type="text" class="form-control" id="edit-addon-display-name" required>
                </div>
                <div class="form-group">
                    <label for="edit-addon-file-type">Type (optional)</label>
                    <input type="text" class="form-control" id="edit-addon-file-type" placeholder="e.g. Plugin, Mod, Resource Pack">
                </div>
                <div class="form-group">
                    <label for="edit-addon-file-location">File location (optional)</label>
                    <input type="text" class="form-control" id="edit-addon-file-location" placeholder="e.g. /plugins, /mods">
                </div>
                <div class="form-group">
                    <label for="edit-addon-product-link">Product link (optional)</label>
                    <input type="url" class="form-control" id="edit-addon-product-link" placeholder="https://...">
                </div>
                <div class="form-group">
                    <label>Cover image (optional)</label>
                    <input type="hidden" id="edit-addon-cover-image-url" value="">
                    <div id="edit-addon-cover-current" class="margin-bottom" style="display:none;"><img id="edit-addon-cover-img" src="" alt="Cover" style="max-height:120px; max-width:200px; border:1px solid rgba(255,255,255,0.2); border-radius:4px;"></div>
                    <input type="file" class="form-control" id="edit-addon-cover-image-file" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
                    <small class="help-block">Upload a new image to replace. JPG, PNG, GIF or WebP, max 5 MB.</small>
                </div>
                <div class="form-group">
                    <label for="edit-addon-source-name">Source name (optional)</label>
                    <input type="text" class="form-control" id="edit-addon-source-name" placeholder="e.g. MC-STORE">
                </div>
                <div class="form-group">
                    <label for="edit-addon-price">Price (optional)</label>
                    <input type="number" class="form-control" id="edit-addon-price" min="0" step="0.01" placeholder="0.00">
                </div>
                <div class="form-group">
                    <label>Placeholders (optional)</label>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-default active" id="edit-addon-ph-mode-manual">Manual</button>
                        <button type="button" class="btn btn-default" id="edit-addon-ph-mode-json">JSON</button>
                    </div>
                    <div id="edit-addon-placeholders-manual-wrap" style="margin-top:0.5rem;">
                        <div id="edit-addon-placeholders-manual-list"></div>
                        <button type="button" class="btn btn-link btn-sm" id="edit-addon-placeholder-add">+ Add placeholder</button>
                    </div>
                    <div id="edit-addon-placeholders-json-wrap" class="hide" style="margin-top:0.5rem;">
                        <textarea id="edit-addon-placeholders-json" class="form-control" rows="5" style="font-family:monospace;font-size:12px;"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="edit-addon-submit"><i class="fa fa-save"></i> Save</button>
            </div>
        </div>
    </div>
</div>

{{-- Update Add-on Version modal --}}
<div id="update-addon-version-modal" class="modal fade" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Upload New Version (Add-on)</h4>
            </div>
            <div class="modal-body">
                <input type="hidden" id="update-addon-version-path" value="">
                <div class="form-group">
                    <label for="update-addon-version-file">New file *</label>
                    <input type="file" class="form-control" id="update-addon-version-file" accept=".zip,.mcaddon,.mcpack,.mctemplate">
                </div>
                <div class="form-group">
                    <label for="update-addon-version-display-name">Name *</label>
                    <input type="text" class="form-control" id="update-addon-version-display-name" required>
                </div>
                <div class="form-group">
                    <label for="update-addon-version-file-type">Type (optional)</label>
                    <input type="text" class="form-control" id="update-addon-version-file-type">
                </div>
                <div class="form-group">
                    <label for="update-addon-version-file-location">File location (optional)</label>
                    <input type="text" class="form-control" id="update-addon-version-file-location">
                </div>
                <div class="form-group">
                    <label for="update-addon-version-product-link">Product link (optional)</label>
                    <input type="url" class="form-control" id="update-addon-version-product-link" placeholder="https://...">
                </div>
                <div class="form-group">
                    <label>Cover image (optional)</label>
                    <input type="hidden" id="update-addon-version-cover-image-url" value="">
                    <div id="update-addon-version-cover-current" class="margin-bottom" style="display:none;"><img id="update-addon-version-cover-img" src="" alt="Cover" style="max-height:120px; max-width:200px; border:1px solid rgba(255,255,255,0.2); border-radius:4px;"></div>
                    <input type="file" class="form-control" id="update-addon-version-cover-image-file" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
                    <small class="help-block">Upload a new image. JPG, PNG, GIF or WebP, max 5 MB.</small>
                </div>
                <div class="form-group">
                    <label for="update-addon-version-source-name">Source name (optional)</label>
                    <input type="text" class="form-control" id="update-addon-version-source-name" placeholder="e.g. MC-STORE">
                </div>
                <div class="form-group">
                    <label for="update-addon-version-price">Price (optional)</label>
                    <input type="number" class="form-control" id="update-addon-version-price" min="0" step="0.01" placeholder="0.00">
                </div>
                <div class="form-group">
                    <label>Placeholders (optional)</label>
                    <textarea id="update-addon-version-placeholders-json" class="form-control" rows="4" style="font-family:monospace;font-size:12px;" placeholder='[{"token":"%%__X__%%","label":"X"}]'></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="update-addon-version-submit"><i class="fa fa-upload"></i> Update version</button>
            </div>
        </div>
    </div>
</div>
@endif
@endsection

@if($license && $license->hasS3Config())
@section('footer-scripts')
    @parent
    <script>
    (function() {
        var $ = window.jQuery;
        if (!$) return;
        var addonsUrl = '{{ route("admin.mcsetups.api.addons") }}';
        var productsUrl = '{{ route("admin.mcsetups.api.products") }}';
        var uploadProductUrl = '{{ route("admin.mcsetups.upload-product") }}';
        var deleteProductUrl = '{{ route("admin.mcsetups.delete-product") }}';
        var updateProductUrl = '{{ route("admin.mcsetups.update-product") }}';
        var updateProductVersionUrl = '{{ route("admin.mcsetups.update-product-version") }}';
        var uploadAddonUrl = '{{ route("admin.mcsetups.upload-addon") }}';
        var updateAddonUrl = '{{ route("admin.mcsetups.update-addon") }}';
        var updateAddonVersionUrl = '{{ route("admin.mcsetups.update-addon-version") }}';
        var deleteAddonUrl = '{{ route("admin.mcsetups.delete-addon") }}';
        var storeBaseUrl = @json($license && $license->store_url ? rtrim($license->store_url, '/') : '');
        var clientProductsUrl = '{{ route("admin.mcsetups.api.client.products") }}';
        var clientAddonsUrl = '{{ route("admin.mcsetups.api.client.addons") }}';
        var mcsetupsLicenseKey = @json($license && $license->license_key ? $license->license_key : '');
        var useClientUploadApi = !!(mcsetupsLicenseKey && storeBaseUrl);
        var csrf = '{{ csrf_token() }}';
        var modalMode = 'product';

        function clientApiHeaders() {
            var h = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf };
            if (mcsetupsLicenseKey) h['X-License-Key'] = mcsetupsLicenseKey;
            return h;
        }
        function clientApiUrl(path, query) {
            var q = (query && mcsetupsLicenseKey) ? '?license_key=' + encodeURIComponent(mcsetupsLicenseKey) : '';
            return path + q;
        }

        function escapeHtml(s) {
            if (!s) return '';
            var div = document.createElement('div');
            div.textContent = s;
            return div.innerHTML;
        }
        function setVal(id, v) {
            var el = document.getElementById(id);
            if (!el) return;
            if (v === null || v === undefined) el.value = '';
            else el.value = String(v);
        }

        var FETCH_TIMEOUT = 10000;

        function fetchWithTimeout(url) {
            var ctrl = new AbortController();
            var t = setTimeout(function() { ctrl.abort(); }, FETCH_TIMEOUT);
            return fetch(url, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                signal: ctrl.signal
            }).then(function(r) {
                clearTimeout(t);
                return r.json();
            });
        }

        function loadProducts() {
            var el = document.getElementById('uploaded-products-list');
            if (!el) return;
            el.innerHTML = '<p class="text-muted"><i class="fa fa-spinner fa-spin"></i> Loading...</p>';
            var listUrl = useClientUploadApi ? clientProductsUrl : productsUrl;
            var listOpts = useClientUploadApi ? { headers: clientApiHeaders(), credentials: 'same-origin' } : { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' };
            var ctrl = new AbortController();
            var t = setTimeout(function() { ctrl.abort(); }, FETCH_TIMEOUT);
            fetch(useClientUploadApi ? clientApiUrl(listUrl) : listUrl, Object.assign({ signal: ctrl.signal }, listOpts))
                .then(function(r) {
                    clearTimeout(t);
                    if (!r.ok) {
                        return r.text().then(function(txt) {
                            var err = new Error('HTTP ' + r.status + (txt ? ': ' + txt.substring(0, 200) : ''));
                            err.status = r.status;
                            throw err;
                        });
                    }
                    return r.json();
                })
                .then(function(data) {
                    var products = useClientUploadApi
                        ? ((data.data && data.data.files) || (data.data && data.data.products) || [])
                        : (data.data && data.data.products) || [];
                    if (useClientUploadApi && Array.isArray(products)) {
                        products = products.filter(function(f) { return f.is_client_upload === true; });
                    }
                    var badge = document.getElementById('products-count-badge');
                    if (badge) badge.textContent = products.length;
                    if (products.length === 0) {
                        el.innerHTML = '<p class="text-muted">No products yet. Click "Add product (setup)" to upload.</p>';
                    } else {
                        var html = '<table class="table table-bordered table-striped"><thead><tr><th>Name</th><th>Author</th><th>Game version</th><th>Category</th><th>URL</th><th>Actions</th></tr></thead><tbody>';
                        products.forEach(function(p) {
                            var path = escapeHtml(p.path || p.file_path || p.object_key || p.key || '');
                            var idVal = p.id != null ? p.id : (p.file_id != null ? p.file_id : (p.product_id != null ? p.product_id : (p.fileId != null ? p.fileId : (p.productId != null ? p.productId : (p._id != null ? p._id : null)))));
                            var id = idVal != null ? String(idVal) : '';
                            var dataJson = escapeHtml(JSON.stringify(p));
                            html += '<tr><td>' + escapeHtml(p.display_name || p.name || '-') + '</td><td>' + escapeHtml(p.author_name || '—') + '</td><td>' + escapeHtml(p.game_version || '—') + '</td><td>' + escapeHtml(p.category || p.product_category || '—') + '</td><td><a href="' + escapeHtml(p.url || '#') + '" target="_blank">Open</a></td><td><button type="button" class="btn btn-default btn-xs edit-product-btn" data-path="' + path + '" data-id="' + id + '" data-json="' + dataJson + '" title="Edit"><i class="fa fa-pencil"></i></button> <button type="button" class="btn btn-default btn-xs update-product-version-btn" data-path="' + path + '" data-json="' + dataJson + '" title="Upload new version"><i class="fa fa-upload"></i></button> <button type="button" class="btn btn-danger btn-xs delete-product-btn" data-path="' + path + '" data-json="' + dataJson + '" title="Delete"><i class="fa fa-trash"></i></button></td></tr>';
                        });
                        html += '</tbody></table>';
                        el.innerHTML = html;
                        el.querySelectorAll('.delete-product-btn').forEach(function(btn) {
                            btn.onclick = function() {
                                var data = {};
                                try { data = JSON.parse(btn.getAttribute('data-json') || '{}'); } catch(e) {}
                                var path = btn.getAttribute('data-path') || data.path || data.file_path || data.object_key || data.key || '';
                                var idRaw = data.id != null ? data.id : (data.file_id != null ? data.file_id : (data.product_id != null ? data.product_id : (data.fileId != null ? data.fileId : (data.productId != null ? data.productId : (data._id != null ? data._id : null)))));
                                var id = idRaw != null ? String(idRaw).replace(/^client-/, '') : null;
                                if (!path && !id) {
                                    alert('Cannot delete: product has no path or id. The store may use a different field name. Check console for product data.');
                                    console.warn('Product data for delete:', data);
                                    return;
                                }
                                if (!confirm('Delete this product? This cannot be undone.')) return;
                                btn.disabled = true;
                                var deleteReq = useClientUploadApi && id
                                    ? fetch(clientProductsUrl + '/' + encodeURIComponent(id) + '/delete', {
                                        method: 'POST',
                                        headers: Object.assign({ 'Content-Type': 'application/json' }, clientApiHeaders()),
                                        credentials: 'same-origin',
                                        body: JSON.stringify({})
                                    })
                                    : fetch(deleteProductUrl, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf }, credentials: 'same-origin', body: JSON.stringify({ path: path, _token: csrf }) });
                                deleteReq.then(function(r) {
                                    var ct = r.headers.get('Content-Type') || '';
                                    if (ct.indexOf('json') !== -1) return r.json();
                                    return r.text().then(function(t) { throw new Error(r.status + ': ' + (t || '').substring(0, 100)); });
                                }).then(function(res) {
                                    if (res && res.success) loadProducts();
                                    else { alert((res && res.error) || 'Delete failed'); btn.disabled = false; }
                                }).catch(function(err) {
                                    alert('Delete failed: ' + (err.message || 'Unknown error'));
                                    btn.disabled = false;
                                });
                            };
                        });
                        el.querySelectorAll('.edit-product-btn').forEach(function(btn) {
                            btn.onclick = function() {
                                var data = {};
                                try { data = JSON.parse(btn.getAttribute('data-json') || '{}'); } catch(e) {}
                                if (useClientUploadApi && data.id != null) {
                                    fetch(clientProductsUrl + '/' + data.id, { headers: clientApiHeaders(), credentials: 'same-origin' })
                                        .then(function(r) { return r.json(); })
                                        .then(function(res) {
                                            var full = (res.data && res.data.product) || (res.data && res.data) || data;
                                            openEditProductModal(full);
                                        })
                                        .catch(function(err) {
                                            openEditProductModal(data);
                                        });
                                } else {
                                    var path = btn.getAttribute('data-path');
                                    var found = (window.mcsetupsProductsList || []).find(function(p) { return (p.path || '') === (path || ''); });
                                    openEditProductModal(found || data);
                                }
                            };
                        });
                        el.querySelectorAll('.update-product-version-btn').forEach(function(btn) {
                            btn.onclick = function() {
                                var data = {};
                                try { data = JSON.parse(btn.getAttribute('data-json') || '{}'); } catch(e) {}
                                openUpdateProductVersionModal(data);
                            };
                        });
                    }
                    window.mcsetupsProductsList = products;
                })
                .catch(function(err) {
                    if (useClientUploadApi && (err.status === 404 || err.status >= 500)) {
                        useClientUploadApi = false;
                        loadProducts();
                        return;
                    }
                    var hint = useClientUploadApi ? 'Store API (/client/products)' : 'S3';
                    var msg = (err && err.name === 'AbortError')
                        ? 'Request timed out. ' + hint + ' may be unreachable.'
                        : (err && err.message ? err.message : 'Could not load. ' + hint + ' may be unreachable.');
                    if (msg.indexOf('HTTP 5') === 0 && msg.indexOf('"error"') !== -1) {
                        try { var m = msg.match(/"error"\s*:\s*"([^"]*)"/); if (m) msg = 'Backend error: ' + m[1]; } catch(e) {}
                    }
                    el.innerHTML = '<p class="text-danger">' + escapeHtml(msg) + '</p><button type="button" class="btn btn-default btn-sm" id="retry-products-btn">Retry</button>';
                    document.getElementById('retry-products-btn').onclick = function() { loadProducts(); };
                });
        }

        function loadAddons() {
            var el = document.getElementById('uploaded-addons-list');
            if (!el) return;
            el.innerHTML = '<p class="text-muted"><i class="fa fa-spinner fa-spin"></i> Loading...</p>';
            var listUrl = useClientUploadApi ? clientAddonsUrl : addonsUrl;
            var listOpts = useClientUploadApi ? { headers: clientApiHeaders(), credentials: 'same-origin' } : { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' };
            var ctrl = new AbortController();
            var t = setTimeout(function() { ctrl.abort(); }, FETCH_TIMEOUT);
            fetch(useClientUploadApi ? clientApiUrl(listUrl) : listUrl, Object.assign({ signal: ctrl.signal }, listOpts))
                .then(function(r) {
                    clearTimeout(t);
                    if (!r.ok) {
                        return r.text().then(function(txt) {
                            var err = new Error('HTTP ' + r.status + (txt ? ': ' + txt.substring(0, 200) : ''));
                            err.status = r.status;
                            throw err;
                        });
                    }
                    return r.json();
                })
                .then(function(data) {
                    var addons = useClientUploadApi
                        ? ((data.data && data.data.addons) || (data.data && data.data.files) || [])
                        : (data.data && data.data.addons) || [];
                    var badge = document.getElementById('addons-count-badge');
                    if (badge) badge.textContent = addons.length;
                    if (addons.length === 0) {
                        el.innerHTML = '<p class="text-muted">No add-ons yet. Click "Add add-on (plugin/pack)" to upload.</p>';
                    } else {
                        var html = '<table class="table table-bordered table-striped"><thead><tr><th>Name</th><th>Type</th><th>Location</th><th>URL</th><th>Actions</th></tr></thead><tbody>';
                        addons.forEach(function(a) {
                            var path = escapeHtml(a.path || a.file_path || a.object_key || a.key || '');
                            var idVal = a.id != null ? a.id : (a.file_id != null ? a.file_id : (a.addon_id != null ? a.addon_id : (a.fileId != null ? a.fileId : (a.addonId != null ? a.addonId : (a._id != null ? a._id : null)))));
                            var id = idVal != null ? String(idVal) : '';
                            var dataJson = escapeHtml(JSON.stringify(a));
                            html += '<tr><td>' + escapeHtml(a.display_name || a.name || '-') + '</td><td>' + escapeHtml(a.file_type || '—') + '</td><td>' + escapeHtml(a.file_location || '—') + '</td><td><a href="' + escapeHtml(a.url || '#') + '" target="_blank">Open</a></td><td><button type="button" class="btn btn-default btn-xs edit-addon-btn" data-path="' + path + '" data-id="' + id + '" data-json="' + dataJson + '" title="Edit"><i class="fa fa-pencil"></i></button> <button type="button" class="btn btn-default btn-xs update-addon-version-btn" data-path="' + path + '" data-json="' + dataJson + '" title="Upload new version"><i class="fa fa-upload"></i></button> <button type="button" class="btn btn-danger btn-xs delete-addon-btn" data-path="' + path + '" data-json="' + dataJson + '" title="Delete"><i class="fa fa-trash"></i></button></td></tr>';
                        });
                        html += '</tbody></table>';
                        el.innerHTML = html;
                        el.querySelectorAll('.delete-addon-btn').forEach(function(btn) {
                            btn.onclick = function() {
                                var data = {};
                                try { data = JSON.parse(btn.getAttribute('data-json') || '{}'); } catch(e) {}
                                var path = btn.getAttribute('data-path') || data.path || data.file_path || data.object_key || data.key || '';
                                var idRaw = data.id != null ? data.id : (data.file_id != null ? data.file_id : (data.addon_id != null ? data.addon_id : (data.fileId != null ? data.fileId : (data.addonId != null ? data.addonId : (data._id != null ? data._id : null)))));
                                var id = idRaw != null ? String(idRaw).replace(/^client-/, '') : null;
                                if (!path && !id) {
                                    alert('Cannot delete: add-on has no path or id. Check console for addon data.');
                                    console.warn('Addon data for delete:', data);
                                    return;
                                }
                                if (!confirm('Delete this add-on? This cannot be undone.')) return;
                                btn.disabled = true;
                                var deleteReq = useClientUploadApi && id
                                    ? fetch(clientAddonsUrl + '/' + encodeURIComponent(id) + '/delete', {
                                        method: 'POST',
                                        headers: Object.assign({ 'Content-Type': 'application/json' }, clientApiHeaders()),
                                        credentials: 'same-origin',
                                        body: JSON.stringify({})
                                    })
                                    : fetch(deleteAddonUrl, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf }, credentials: 'same-origin', body: JSON.stringify({ path: path, _token: csrf }) });
                                deleteReq.then(function(r) {
                                    var ct = r.headers.get('Content-Type') || '';
                                    if (ct.indexOf('json') !== -1) return r.json();
                                    return r.text().then(function(t) { throw new Error(r.status + ': ' + (t || '').substring(0, 100)); });
                                }).then(function(res) {
                                    if (res && res.success) loadAddons();
                                    else { alert((res && res.error) || 'Delete failed'); btn.disabled = false; }
                                }).catch(function(err) {
                                    alert('Delete failed: ' + (err.message || 'Unknown error'));
                                    btn.disabled = false;
                                });
                            };
                        });
                        el.querySelectorAll('.edit-addon-btn').forEach(function(btn) {
                            btn.onclick = function() {
                                var data = {};
                                try { data = JSON.parse(btn.getAttribute('data-json') || '{}'); } catch(e) {}
                                if (useClientUploadApi && data.id != null) {
                                    fetch(clientAddonsUrl + '/' + data.id, { headers: clientApiHeaders(), credentials: 'same-origin' })
                                        .then(function(r) { return r.json(); })
                                        .then(function(res) {
                                            var full = (res.data && res.data.addon) || (res.data && res.data) || data;
                                            openEditAddonModal(full);
                                        })
                                        .catch(function(err) {
                                            openEditAddonModal(data);
                                        });
                                } else {
                                    var path = btn.getAttribute('data-path');
                                    fetch(addonsUrl + '?fresh=1', { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                                        .then(function(r) { return r.json(); })
                                        .then(function(res) {
                                            var list = (res.data && res.data.addons) || [];
                                            window.mcsetupsAddonsList = list;
                                            var fresh = list.find(function(a) { return (a.path || '') === path; });
                                            openEditAddonModal(fresh || data || { path: path });
                                        })
                                        .catch(function() { openEditAddonModal(data || { path: path }); });
                                }
                            };
                        });
                        el.querySelectorAll('.update-addon-version-btn').forEach(function(btn) {
                            btn.onclick = function() {
                                var data = {};
                                try { data = JSON.parse(btn.getAttribute('data-json') || '{}'); } catch(e) {}
                                openUpdateAddonVersionModal(data);
                            };
                        });
                    }
                    window.mcsetupsAddonsList = addons;
                })
                .catch(function(err) {
                    if (useClientUploadApi && (err.status === 404 || err.status >= 500)) {
                        useClientUploadApi = false;
                        loadAddons();
                        return;
                    }
                    var hint = useClientUploadApi ? 'Store API (/client/addons)' : 'S3';
                    var msg = (err && err.name === 'AbortError')
                        ? 'Request timed out. ' + hint + ' may be unreachable.'
                        : (err && err.message ? err.message : 'Could not load. ' + hint + ' may be unreachable.');
                    if (msg.indexOf('HTTP 5') === 0 && msg.indexOf('"error"') !== -1) {
                        try { var m = msg.match(/"error"\s*:\s*"([^"]*)"/); if (m) msg = 'Backend error: ' + m[1]; } catch(e) {}
                    }
                    el.innerHTML = '<p class="text-danger">' + escapeHtml(msg) + '</p><button type="button" class="btn btn-default btn-sm" id="retry-addons-btn">Retry</button>';
                    document.getElementById('retry-addons-btn').onclick = function() { loadAddons(); };
                });
        }

        function fillAddonCheckboxes(containerId, addons, selectedPaths) {
            var div = document.getElementById(containerId);
            if (!div) return;
            div.innerHTML = '';
            (addons || []).forEach(function(a) {
                var id = (a.id != null ? String(a.id) : '') || a.path || a.name || '';
                var label = a.display_name || a.name || a.path || id;
                var checked = (selectedPaths || []).some(function(s) { return String(s) === String(id); }) ? ' checked' : '';
                var lbl = document.createElement('label');
                lbl.style.display = 'block';
                lbl.style.marginBottom = '6px';
                lbl.style.cursor = 'pointer';
                lbl.innerHTML = '<input type="checkbox" value="' + escapeHtml(id) + '"' + checked + '> ' + escapeHtml(label);
                div.appendChild(lbl);
            });
            if (!addons || addons.length === 0) {
                div.innerHTML = '<p class="text-muted" style="font-size:12px;">No add-ons yet.</p>';
            }
        }

        function openEditProductModal(p) {
            setVal('edit-product-path', p.path || '');
            setVal('edit-product-id', p.id != null ? p.id : '');
            setVal('edit-product-display-name', p.display_name || p.name);
            setVal('edit-product-description', p.description);
            setVal('edit-product-author-name', p.author_name);
            setVal('edit-product-game-version', p.game_version);
            setVal('edit-product-category', p.category);
            setVal('edit-product-product-link', p.product_link);
            setVal('edit-product-cover-image-url', p.cover_image_url || '');
            var coverWrap = document.getElementById('edit-product-cover-current');
            var coverImg = document.getElementById('edit-product-cover-img');
            if (p.cover_image_url) { coverWrap.style.display = 'block'; coverImg.src = p.cover_image_url; } else { coverWrap.style.display = 'none'; coverImg.src = ''; }
            document.getElementById('edit-product-cover-image-file').value = '';
            setVal('edit-product-source-name', p.source_name);
            setVal('edit-product-price', p.price != null && p.price !== '' ? p.price : '');
            window._editProductPlaceholders = Array.isArray(p.placeholders) ? p.placeholders.slice() : [];
            renderEditProductPlaceholdersManual();
            document.getElementById('edit-product-placeholders-manual-wrap').classList.remove('hide');
            document.getElementById('edit-product-placeholders-json-wrap').classList.add('hide');
            document.getElementById('edit-product-placeholders-json').value = JSON.stringify(window._editProductPlaceholders, null, 2);
            var addonListUrl = useClientUploadApi ? clientAddonsUrl : addonsUrl;
            var addonListOpts = useClientUploadApi ? { headers: clientApiHeaders(), credentials: 'same-origin' } : { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' };
            fetch(useClientUploadApi ? clientApiUrl(addonListUrl) : addonListUrl, addonListOpts)
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    var addons = (data.data && data.data.addons) || (data.data && data.data.files) || [];
                    fillAddonCheckboxes('edit-product-required-addons', addons, p.required_addon_ids || []);
                    fillAddonCheckboxes('edit-product-optional-addons', addons, p.optional_addon_ids || []);
                })
                .catch(function() {
                    fillAddonCheckboxes('edit-product-required-addons', [], []);
                    fillAddonCheckboxes('edit-product-optional-addons', [], []);
                });
            $('#edit-product-modal').modal('show');
        }

        function renderEditProductPlaceholdersManual() {
            var list = window._editProductPlaceholders || [];
            var wrap = document.getElementById('edit-product-placeholders-manual-list');
            if (!wrap) return;
            wrap.innerHTML = '';
            list.forEach(function(ph, i) {
                var row = document.createElement('div');
                row.className = 'form-group ph-row';
                row.style.marginBottom = '8px';
                row.style.padding = '8px';
                row.style.border = '1px solid rgba(255,255,255,0.15)';
                row.style.borderRadius = '4px';
                row.style.background = 'rgba(0,0,0,0.2)';
                row.innerHTML = '<div class="row"><div class="col-sm-6"><input type="text" class="form-control input-sm edit-ph" data-i="' + i + '" data-f="token" placeholder="Token" value="' + escapeHtml(ph.token || '') + '"></div><div class="col-sm-4"><input type="text" class="form-control input-sm edit-ph" data-i="' + i + '" data-f="label" placeholder="Label" value="' + escapeHtml(ph.label || '') + '"></div><div class="col-sm-2"><button type="button" class="btn btn-danger btn-sm edit-ph-remove" data-i="' + i + '">Remove</button></div></div>';
                wrap.appendChild(row);
            });
            wrap.querySelectorAll('.edit-ph').forEach(function(inp) {
                inp.oninput = function() {
                    var i = parseInt(this.getAttribute('data-i'), 10);
                    var f = this.getAttribute('data-f');
                    if (window._editProductPlaceholders[i]) window._editProductPlaceholders[i][f] = this.value;
                };
            });
            wrap.querySelectorAll('.edit-ph-remove').forEach(function(btn) {
                btn.onclick = function() {
                    var i = parseInt(btn.getAttribute('data-i'), 10);
                    window._editProductPlaceholders.splice(i, 1);
                    renderEditProductPlaceholdersManual();
                };
            });
        }

        function openUpdateProductVersionModal(p) {
            setVal('update-product-version-path', p.path);
            setVal('update-product-version-display-name', p.display_name || p.name);
            setVal('update-product-version-description', p.description);
            setVal('update-product-version-author-name', p.author_name);
            setVal('update-product-version-game-version', p.game_version);
            setVal('update-product-version-category', p.category);
            setVal('update-product-version-product-link', p.product_link);
            setVal('update-product-version-cover-image-url', p.cover_image_url || '');
            var coverWrap = document.getElementById('update-product-version-cover-current');
            var coverImg = document.getElementById('update-product-version-cover-img');
            if (p.cover_image_url) { coverWrap.style.display = 'block'; coverImg.src = p.cover_image_url; } else { coverWrap.style.display = 'none'; coverImg.src = ''; }
            document.getElementById('update-product-version-cover-image-file').value = '';
            setVal('update-product-version-source-name', p.source_name);
            setVal('update-product-version-price', p.price != null && p.price !== '' ? p.price : '');
            setVal('update-product-version-file', '');
            fetch(addonsUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    var addons = (data.data && data.data.addons) || [];
                    fillAddonCheckboxes('update-product-version-required-addons', addons, p.required_addon_ids || []);
                    fillAddonCheckboxes('update-product-version-optional-addons', addons, p.optional_addon_ids || []);
                })
                .catch(function() {
                    fillAddonCheckboxes('update-product-version-required-addons', [], []);
                    fillAddonCheckboxes('update-product-version-optional-addons', [], []);
                });
            $('#update-product-version-modal').modal('show');
        }

        function openEditAddonModal(a) {
            if (!a) a = {};
            setVal('edit-addon-path', a.path || '');
            setVal('edit-addon-id', a.id != null ? a.id : '');
            setVal('edit-addon-display-name', a.display_name != null ? a.display_name : (a.name != null ? a.name : ''));
            setVal('edit-addon-file-type', a.file_type != null ? a.file_type : '');
            setVal('edit-addon-file-location', a.file_location != null ? a.file_location : '');
            setVal('edit-addon-product-link', a.product_link != null ? a.product_link : '');
            setVal('edit-addon-cover-image-url', a.cover_image_url != null ? a.cover_image_url : '');
            var coverWrap = document.getElementById('edit-addon-cover-current');
            var coverImg = document.getElementById('edit-addon-cover-img');
            if (a.cover_image_url) { coverWrap.style.display = 'block'; coverImg.src = a.cover_image_url; } else { coverWrap.style.display = 'none'; coverImg.src = ''; }
            document.getElementById('edit-addon-cover-image-file').value = '';
            setVal('edit-addon-source-name', a.source_name != null ? a.source_name : '');
            setVal('edit-addon-price', a.price != null && a.price !== '' ? a.price : '');
            window._editAddonPlaceholders = Array.isArray(a.placeholders) ? a.placeholders.slice() : [];
            var manualWrap = document.getElementById('edit-addon-placeholders-manual-wrap');
            var jsonWrap = document.getElementById('edit-addon-placeholders-json-wrap');
            if (manualWrap) manualWrap.classList.remove('hide');
            if (jsonWrap) jsonWrap.classList.add('hide');
            document.getElementById('edit-addon-placeholders-json').value = JSON.stringify(window._editAddonPlaceholders, null, 2);
            renderEditAddonPlaceholdersManual();
            $('#edit-addon-modal').modal('show');
        }

        function renderEditAddonPlaceholdersManual() {
            var listEl = document.getElementById('edit-addon-placeholders-manual-list');
            if (!listEl) return;
            var list = window._editAddonPlaceholders || [];
            listEl.innerHTML = '';
            list.forEach(function(ph, i) {
                var row = document.createElement('div');
                row.className = 'form-group';
                row.style.marginBottom = '8px';
                row.style.padding = '8px';
                row.style.border = '1px solid rgba(255,255,255,0.15)';
                row.style.borderRadius = '4px';
                row.style.background = 'rgba(0,0,0,0.2)';
                row.innerHTML = '<div class="row"><div class="col-sm-6"><input type="text" class="form-control input-sm edit-addon-ph" data-i="' + i + '" data-f="token" placeholder="Token" value="' + escapeHtml(ph.token || '') + '"></div><div class="col-sm-4"><input type="text" class="form-control input-sm edit-addon-ph" data-i="' + i + '" data-f="label" placeholder="Label" value="' + escapeHtml(ph.label || '') + '"></div><div class="col-sm-2"><button type="button" class="btn btn-danger btn-sm edit-addon-ph-remove" data-i="' + i + '">Remove</button></div></div>';
                listEl.appendChild(row);
            });
            listEl.querySelectorAll('.edit-addon-ph').forEach(function(inp) {
                inp.oninput = function() {
                    var i = parseInt(this.getAttribute('data-i'), 10);
                    var f = this.getAttribute('data-f');
                    if (window._editAddonPlaceholders[i]) window._editAddonPlaceholders[i][f] = this.value;
                };
            });
            listEl.querySelectorAll('.edit-addon-ph-remove').forEach(function(btn) {
                btn.onclick = function() {
                    var i = parseInt(btn.getAttribute('data-i'), 10);
                    window._editAddonPlaceholders.splice(i, 1);
                    renderEditAddonPlaceholdersManual();
                };
            });
        }
        var editAddonPhAdd = document.getElementById('edit-addon-placeholder-add');
        if (editAddonPhAdd) editAddonPhAdd.onclick = function() {
            window._editAddonPlaceholders = window._editAddonPlaceholders || [];
            window._editAddonPlaceholders.push({ token: '', label: '', description: '', example: '' });
            renderEditAddonPlaceholdersManual();
        };
        var editProductPhAdd = document.getElementById('edit-product-placeholder-add');
        if (editProductPhAdd) editProductPhAdd.onclick = function() {
            window._editProductPlaceholders = window._editProductPlaceholders || [];
            window._editProductPlaceholders.push({ token: '', label: '', description: '', example: '' });
            renderEditProductPlaceholdersManual();
        };

        function openUpdateAddonVersionModal(a) {
            setVal('update-addon-version-path', a.path);
            setVal('update-addon-version-display-name', a.display_name || a.name);
            setVal('update-addon-version-file-type', a.file_type);
            setVal('update-addon-version-file-location', a.file_location);
            setVal('update-addon-version-product-link', a.product_link);
            setVal('update-addon-version-cover-image-url', a.cover_image_url || '');
            var coverWrap = document.getElementById('update-addon-version-cover-current');
            var coverImg = document.getElementById('update-addon-version-cover-img');
            if (a.cover_image_url) { coverWrap.style.display = 'block'; coverImg.src = a.cover_image_url; } else { coverWrap.style.display = 'none'; coverImg.src = ''; }
            document.getElementById('update-addon-version-cover-image-file').value = '';
            setVal('update-addon-version-source-name', a.source_name);
            setVal('update-addon-version-price', a.price != null && a.price !== '' ? a.price : '');
            document.getElementById('update-addon-version-placeholders-json').value = JSON.stringify(a.placeholders || [], null, 2);
            setVal('update-addon-version-file', '');
            $('#update-addon-version-modal').modal('show');
        }

        document.getElementById('edit-product-ph-mode-manual').onclick = function() {
            document.getElementById('edit-product-placeholders-manual-wrap').classList.remove('hide');
            document.getElementById('edit-product-placeholders-json-wrap').classList.add('hide');
        };
        document.getElementById('edit-product-ph-mode-json').onclick = function() {
            document.getElementById('edit-product-placeholders-manual-wrap').classList.add('hide');
            document.getElementById('edit-product-placeholders-json-wrap').classList.remove('hide');
            document.getElementById('edit-product-placeholders-json').value = JSON.stringify(window._editProductPlaceholders || [], null, 2);
        };
        document.getElementById('edit-addon-ph-mode-manual').onclick = function() {
            document.getElementById('edit-addon-placeholders-manual-wrap').classList.remove('hide');
            document.getElementById('edit-addon-placeholders-json-wrap').classList.add('hide');
        };
        document.getElementById('edit-addon-ph-mode-json').onclick = function() {
            document.getElementById('edit-addon-placeholders-manual-wrap').classList.add('hide');
            document.getElementById('edit-addon-placeholders-json-wrap').classList.remove('hide');
            document.getElementById('edit-addon-placeholders-json').value = JSON.stringify(window._editAddonPlaceholders || [], null, 2);
        };

        document.getElementById('edit-product-submit').onclick = function() {
            var path = document.getElementById('edit-product-path').value;
            var editProductId = document.getElementById('edit-product-id').value;
            if (!path && !editProductId) return;
            var placeholders = window._editProductPlaceholders || [];
            if (document.getElementById('edit-product-placeholders-json-wrap').classList.contains('hide') === false) {
                try {
                    placeholders = JSON.parse(document.getElementById('edit-product-placeholders-json').value.trim() || '[]');
                } catch (e) { placeholders = []; }
            }
            var requiredIds = [];
            document.querySelectorAll('#edit-product-required-addons input:checked').forEach(function(cb) { requiredIds.push(cb.value); });
            var optionalIds = [];
            document.querySelectorAll('#edit-product-optional-addons input:checked').forEach(function(cb) { optionalIds.push(cb.value); });
            var coverFileInput = document.getElementById('edit-product-cover-image-file');
            var hasCoverFile = coverFileInput.files && coverFileInput.files[0];
            this.disabled = true;
            var btn = this;
            if (useClientUploadApi && editProductId) {
                var payload = {
                    display_name: document.getElementById('edit-product-display-name').value.trim(),
                    description: document.getElementById('edit-product-description').value.trim(),
                    author_name: document.getElementById('edit-product-author-name').value.trim(),
                    game_version: document.getElementById('edit-product-game-version').value.trim(),
                    category: document.getElementById('edit-product-category').value.trim(),
                    cover_image_url: document.getElementById('edit-product-cover-image-url').value.trim() || undefined,
                    placeholders: placeholders,
                    required_addon_ids: requiredIds,
                    optional_addon_ids: optionalIds
                };
                fetch(clientProductsUrl + '/' + editProductId, {
                    method: 'PATCH',
                    headers: Object.assign({ 'Content-Type': 'application/json' }, clientApiHeaders()),
                    credentials: 'same-origin',
                    body: JSON.stringify(payload)
                }).then(function(r) { return r.json(); }).then(function(res) {
                    btn.disabled = false;
                    if (res.success || (res.data && res.data.product)) { $('#edit-product-modal').modal('hide'); loadProducts(); }
                    else alert(res.error || res.message || 'Update failed');
                }).catch(function(err) {
                    btn.disabled = false;
                    alert('Update failed: ' + (err && err.message || 'Unknown error'));
                });
                return;
            }
            if (hasCoverFile) {
                var fd = new FormData();
                fd.append('_token', csrf);
                fd.append('path', path);
                fd.append('display_name', document.getElementById('edit-product-display-name').value.trim());
                fd.append('description', document.getElementById('edit-product-description').value.trim());
                fd.append('author_name', document.getElementById('edit-product-author-name').value.trim());
                fd.append('game_version', document.getElementById('edit-product-game-version').value.trim());
                fd.append('category', document.getElementById('edit-product-category').value.trim());
                fd.append('product_link', document.getElementById('edit-product-product-link').value.trim());
                fd.append('cover_image', coverFileInput.files[0]);
                fd.append('source_name', document.getElementById('edit-product-source-name').value.trim());
                fd.append('price', document.getElementById('edit-product-price').value.trim() || '');
                fd.append('placeholders', JSON.stringify(placeholders));
                fd.append('required_addon_ids', JSON.stringify(requiredIds));
                fd.append('optional_addon_ids', JSON.stringify(optionalIds));
                fetch(updateProductUrl, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                    .then(function(r) { return r.json(); }).then(function(res) {
                        btn.disabled = false;
                        if (res.success) { $('#edit-product-modal').modal('hide'); loadProducts(); }
                        else alert(res.error || 'Update failed');
                    }).catch(function(err) {
                        btn.disabled = false;
                        alert('Update failed: ' + (err && err.message || 'Unknown error'));
                    });
            } else {
                var payload = {
                    path: path,
                    _token: csrf,
                    display_name: document.getElementById('edit-product-display-name').value.trim(),
                    description: document.getElementById('edit-product-description').value.trim(),
                    author_name: document.getElementById('edit-product-author-name').value.trim(),
                    game_version: document.getElementById('edit-product-game-version').value.trim(),
                    category: document.getElementById('edit-product-category').value.trim(),
                    product_link: document.getElementById('edit-product-product-link').value.trim(),
                    cover_image_url: document.getElementById('edit-product-cover-image-url').value.trim(),
                    source_name: document.getElementById('edit-product-source-name').value.trim(),
                    price: document.getElementById('edit-product-price').value.trim() || null,
                    placeholders: JSON.stringify(placeholders),
                    required_addon_ids: JSON.stringify(requiredIds),
                    optional_addon_ids: JSON.stringify(optionalIds)
                };
                fetch(updateProductUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf },
                    credentials: 'same-origin',
                    body: JSON.stringify(payload)
                }).then(function(r) { return r.json(); }).then(function(res) {
                    btn.disabled = false;
                    if (res.success) { $('#edit-product-modal').modal('hide'); loadProducts(); }
                    else alert(res.error || 'Update failed');
                }).catch(function(err) {
                    btn.disabled = false;
                    alert('Update failed: ' + (err && err.message || 'Unknown error'));
                });
            }
        };

        document.getElementById('update-product-version-submit').onclick = function() {
            var path = document.getElementById('update-product-version-path').value;
            var fileInput = document.getElementById('update-product-version-file');
            if (!path || !fileInput || !fileInput.files || !fileInput.files[0]) {
                alert('Select a new archive file.');
                return;
            }
            var fd = new FormData();
            fd.append('_token', csrf);
            fd.append('path', path);
            fd.append('product_file', fileInput.files[0]);
            fd.append('display_name', document.getElementById('update-product-version-display-name').value.trim());
            fd.append('description', document.getElementById('update-product-version-description').value.trim());
            fd.append('author_name', document.getElementById('update-product-version-author-name').value.trim());
            fd.append('game_version', document.getElementById('update-product-version-game-version').value.trim());
            fd.append('category', document.getElementById('update-product-version-category').value.trim());
            fd.append('product_link', document.getElementById('update-product-version-product-link').value.trim());
            var coverFileInput = document.getElementById('update-product-version-cover-image-file');
            if (coverFileInput.files && coverFileInput.files[0]) {
                fd.append('cover_image', coverFileInput.files[0]);
            } else {
                fd.append('cover_image_url', document.getElementById('update-product-version-cover-image-url').value.trim());
            }
            fd.append('source_name', document.getElementById('update-product-version-source-name').value.trim());
            fd.append('price', document.getElementById('update-product-version-price').value.trim() || '');
            var requiredIds = [];
            document.querySelectorAll('#update-product-version-required-addons input:checked').forEach(function(cb) { requiredIds.push(cb.value); });
            var optionalIds = [];
            document.querySelectorAll('#update-product-version-optional-addons input:checked').forEach(function(cb) { optionalIds.push(cb.value); });
            fd.append('required_addon_ids', JSON.stringify(requiredIds));
            fd.append('optional_addon_ids', JSON.stringify(optionalIds));
            fd.append('placeholders', JSON.stringify([]));
            this.disabled = true;
            var btn = this;
            fetch(updateProductVersionUrl, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    btn.disabled = false;
                    if (res.success) { $('#update-product-version-modal').modal('hide'); loadProducts(); }
                    else alert(res.error || 'Update failed');
                })
                .catch(function(err) { btn.disabled = false; alert('Update failed: ' + (err && err.message || 'Unknown error')); });
        };

        document.getElementById('edit-addon-submit').onclick = function() {
            var path = document.getElementById('edit-addon-path').value;
            var editAddonId = document.getElementById('edit-addon-id').value;
            if (!path && !editAddonId) return;
            var placeholders = window._editAddonPlaceholders || [];
            if (document.getElementById('edit-addon-placeholders-json-wrap') && !document.getElementById('edit-addon-placeholders-json-wrap').classList.contains('hide')) {
                try {
                    placeholders = JSON.parse(document.getElementById('edit-addon-placeholders-json').value.trim() || '[]');
                } catch (e) { placeholders = []; }
            }
            var coverFileInput = document.getElementById('edit-addon-cover-image-file');
            var hasCoverFile = coverFileInput.files && coverFileInput.files[0];
            this.disabled = true;
            var btn = this;
            if (useClientUploadApi && editAddonId) {
                var payload = {
                    display_name: document.getElementById('edit-addon-display-name').value.trim(),
                    placeholders: placeholders,
                    file_type: document.getElementById('edit-addon-file-type').value.trim() || undefined,
                    file_location: document.getElementById('edit-addon-file-location').value.trim() || undefined
                };
                fetch(clientAddonsUrl + '/' + editAddonId, {
                    method: 'PATCH',
                    headers: Object.assign({ 'Content-Type': 'application/json' }, clientApiHeaders()),
                    credentials: 'same-origin',
                    body: JSON.stringify(payload)
                }).then(function(r) { return r.json(); }).then(function(res) {
                    btn.disabled = false;
                    if (res.success || (res.data && res.data.addon)) { $('#edit-addon-modal').modal('hide'); loadAddons(); }
                    else alert(res.error || res.message || 'Update failed');
                }).catch(function(err) {
                    btn.disabled = false;
                    alert('Update failed: ' + (err && err.message || 'Unknown error'));
                });
                return;
            }
            if (hasCoverFile) {
                var fd = new FormData();
                fd.append('_token', csrf);
                fd.append('path', path);
                fd.append('display_name', document.getElementById('edit-addon-display-name').value.trim());
                fd.append('file_type', document.getElementById('edit-addon-file-type').value.trim());
                fd.append('file_location', document.getElementById('edit-addon-file-location').value.trim());
                fd.append('product_link', document.getElementById('edit-addon-product-link').value.trim());
                fd.append('cover_image', coverFileInput.files[0]);
                fd.append('source_name', document.getElementById('edit-addon-source-name').value.trim());
                fd.append('price', document.getElementById('edit-addon-price').value.trim() || '');
                fd.append('placeholders', JSON.stringify(placeholders));
                fetch(updateAddonUrl, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                    .then(function(r) { return r.json(); }).then(function(res) {
                        btn.disabled = false;
                        if (res.success) { $('#edit-addon-modal').modal('hide'); loadAddons(); }
                        else alert(res.error || 'Update failed');
                    }).catch(function(err) {
                        btn.disabled = false;
                        alert('Update failed: ' + (err && err.message || 'Unknown error'));
                    });
            } else {
                var payload = {
                    path: path,
                    _token: csrf,
                    display_name: document.getElementById('edit-addon-display-name').value.trim(),
                    file_type: document.getElementById('edit-addon-file-type').value.trim(),
                    file_location: document.getElementById('edit-addon-file-location').value.trim(),
                    product_link: document.getElementById('edit-addon-product-link').value.trim(),
                    cover_image_url: document.getElementById('edit-addon-cover-image-url').value.trim(),
                    source_name: document.getElementById('edit-addon-source-name').value.trim(),
                    price: document.getElementById('edit-addon-price').value.trim() || null,
                    placeholders: JSON.stringify(placeholders)
                };
                fetch(updateAddonUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf },
                    credentials: 'same-origin',
                    body: JSON.stringify(payload)
                }).then(function(r) { return r.json(); }).then(function(res) {
                    btn.disabled = false;
                    if (res.success) { $('#edit-addon-modal').modal('hide'); loadAddons(); }
                    else alert(res.error || 'Update failed');
                }).catch(function(err) {
                    btn.disabled = false;
                    alert('Update failed: ' + (err && err.message || 'Unknown error'));
                });
            }
        };

        document.getElementById('update-addon-version-submit').onclick = function() {
            var path = document.getElementById('update-addon-version-path').value;
            var fileInput = document.getElementById('update-addon-version-file');
            if (!path || !fileInput || !fileInput.files || !fileInput.files[0]) {
                alert('Select a new file.');
                return;
            }
            var placeholders = [];
            try {
                placeholders = JSON.parse(document.getElementById('update-addon-version-placeholders-json').value.trim() || '[]');
            } catch (e) {}
            var fd = new FormData();
            fd.append('_token', csrf);
            fd.append('path', path);
            fd.append('addon_file', fileInput.files[0]);
            fd.append('display_name', document.getElementById('update-addon-version-display-name').value.trim());
            fd.append('file_type', document.getElementById('update-addon-version-file-type').value.trim());
            fd.append('file_location', document.getElementById('update-addon-version-file-location').value.trim());
            fd.append('product_link', document.getElementById('update-addon-version-product-link').value.trim());
            var coverFileInput = document.getElementById('update-addon-version-cover-image-file');
            if (coverFileInput.files && coverFileInput.files[0]) {
                fd.append('cover_image', coverFileInput.files[0]);
            } else {
                fd.append('cover_image_url', document.getElementById('update-addon-version-cover-image-url').value.trim());
            }
            fd.append('source_name', document.getElementById('update-addon-version-source-name').value.trim());
            fd.append('price', document.getElementById('update-addon-version-price').value.trim() || '');
            fd.append('placeholders', JSON.stringify(placeholders));
            this.disabled = true;
            var btn = this;
            fetch(updateAddonVersionUrl, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    btn.disabled = false;
                    if (res.success) { $('#update-addon-version-modal').modal('hide'); loadAddons(); }
                    else alert(res.error || 'Update failed');
                })
                .catch(function(err) { btn.disabled = false; alert('Update failed: ' + (err && err.message || 'Unknown error')); });
        };

        $('a[href="#tab-products"]').on('shown.bs.tab', function() { loadProducts(); });
        $('a[href="#tab-addons"]').on('shown.bs.tab', function() { loadAddons(); });
        loadProducts();

        var addProductPlaceholderMode = 'manual';
        var addProductPlaceholdersList = [];

        function setModalMode(mode) {
            modalMode = mode;
            var productFields = document.getElementById('modal-product-fields');
            var addonFields = document.getElementById('modal-addon-fields');
            var descWrap = document.getElementById('modal-description-wrap');
            var authorWrap = document.getElementById('modal-author-wrap');
            var addonsWrap = document.getElementById('modal-product-addons-wrap');
            var addonsOptWrap = document.getElementById('modal-product-addons-opt-wrap');
            var gameWrap = document.getElementById('modal-game-version-wrap');
            var title = document.getElementById('add-modal-title');
            if (mode === 'product') {
                if (productFields) productFields.classList.remove('hide');
                if (addonFields) addonFields.classList.add('hide');
                if (descWrap) descWrap.classList.remove('hide');
                if (authorWrap) authorWrap.classList.remove('hide');
                if (addonsWrap) addonsWrap.classList.remove('hide');
                if (addonsOptWrap) addonsOptWrap.classList.remove('hide');
                if (gameWrap) gameWrap.classList.remove('hide');
                if (title) title.textContent = 'Add Product';
                var pf = document.getElementById('add-product-file');
                var af = document.getElementById('add-addon-file');
                if (pf) pf.required = true;
                if (af) af.required = false;
            } else {
                if (productFields) productFields.classList.add('hide');
                if (addonFields) addonFields.classList.remove('hide');
                if (descWrap) descWrap.classList.add('hide');
                if (authorWrap) authorWrap.classList.add('hide');
                if (addonsWrap) addonsWrap.classList.add('hide');
                if (addonsOptWrap) addonsOptWrap.classList.add('hide');
                if (gameWrap) gameWrap.classList.add('hide');
                if (title) title.textContent = 'Add Addon';
                var pf = document.getElementById('add-product-file');
                var af = document.getElementById('add-addon-file');
                if (pf) pf.required = false;
                if (af) af.required = true;
            }
        }

        function renderAddProductPlaceholdersManual() {
            var listEl = document.getElementById('add-product-placeholders-manual-list');
            if (!listEl) return;
            listEl.innerHTML = '';
            addProductPlaceholdersList.forEach(function(p, i) {
                var row = document.createElement('div');
                row.className = 'form-group ph-row';
                row.style.marginBottom = '8px';
                row.style.padding = '8px';
                row.style.border = '1px solid rgba(255,255,255,0.15)';
                row.style.borderRadius = '4px';
                row.style.background = 'rgba(0,0,0,0.2)';
                row.innerHTML = '<div class="row"><div class="col-sm-6"><input type="text" class="form-control input-sm" data-ph-i="' + i + '" data-ph-field="token" placeholder="Token (e.g. %%__SERVER_NAME__%%)" value="' + escapeHtml(p.token || '') + '"></div><div class="col-sm-4"><input type="text" class="form-control input-sm" data-ph-i="' + i + '" data-ph-field="label" placeholder="Label" value="' + escapeHtml(p.label || '') + '"></div><div class="col-sm-2"><button type="button" class="btn btn-danger btn-sm ph-remove" data-i="' + i + '">Remove</button></div></div><div class="row" style="margin-top:4px;"><div class="col-sm-6"><input type="text" class="form-control input-sm" data-ph-i="' + i + '" data-ph-field="description" placeholder="Description" value="' + escapeHtml(p.description || '') + '"></div><div class="col-sm-6"><input type="text" class="form-control input-sm" data-ph-i="' + i + '" data-ph-field="example" placeholder="Example" value="' + escapeHtml(p.example || '') + '"></div></div>';
                listEl.appendChild(row);
            });
            listEl.querySelectorAll('[data-ph-field]').forEach(function(inp) {
                inp.oninput = function() {
                    var i = parseInt(this.getAttribute('data-ph-i'), 10);
                    var field = this.getAttribute('data-ph-field');
                    if (addProductPlaceholdersList[i]) addProductPlaceholdersList[i][field] = this.value;
                };
            });
            listEl.querySelectorAll('.ph-remove').forEach(function(btn) {
                btn.onclick = function() {
                    var i = parseInt(btn.getAttribute('data-i'), 10);
                    addProductPlaceholdersList.splice(i, 1);
                    renderAddProductPlaceholdersManual();
                };
            });
        }

        function setAddProductPlaceholderMode(mode) {
            addProductPlaceholderMode = mode;
            var manualWrap = document.getElementById('add-product-placeholders-manual-wrap');
            var jsonWrap = document.getElementById('add-product-placeholders-json-wrap');
            if (manualWrap && jsonWrap) {
                if (mode === 'manual') {
                    manualWrap.classList.remove('hide');
                    jsonWrap.classList.add('hide');
                } else {
                    manualWrap.classList.add('hide');
                    jsonWrap.classList.remove('hide');
                    document.getElementById('add-product-placeholders-json').value = JSON.stringify(addProductPlaceholdersList, null, 2);
                }
            }
        }

        function loadAddProductAddons() {
            var requiredDiv = document.getElementById('add-product-required-addons');
            var optionalDiv = document.getElementById('add-product-optional-addons');
            if (!requiredDiv || !optionalDiv) return;
            var addons = window.mcsetupsAddonsList || [];
            requiredDiv.innerHTML = '';
            optionalDiv.innerHTML = '';
            addons.forEach(function(a, idx) {
                var id = a.path || a.name || String(idx);
                var labelText = escapeHtml(a.display_name || a.name || a.path || id);
                var labelReq = document.createElement('label');
                labelReq.style.display = 'block';
                labelReq.style.marginBottom = '6px';
                labelReq.style.cursor = 'pointer';
                labelReq.innerHTML = '<input type="checkbox" name="addon_required" value="' + escapeHtml(id) + '"> ' + labelText;
                requiredDiv.appendChild(labelReq);
                var labelOpt = document.createElement('label');
                labelOpt.style.display = 'block';
                labelOpt.style.marginBottom = '6px';
                labelOpt.style.cursor = 'pointer';
                labelOpt.innerHTML = '<input type="checkbox" name="addon_optional" value="' + escapeHtml(id) + '"> ' + labelText;
                optionalDiv.appendChild(labelOpt);
            });
            if (addons.length === 0) {
                requiredDiv.innerHTML = '<p class="text-muted" style="font-size:12px;">No addons yet. Upload addons in the Addons tab first.</p>';
                optionalDiv.innerHTML = '<p class="text-muted" style="font-size:12px;">No addons yet. Upload addons in the Addons tab first.</p>';
            }
        }

        function openAddModal(mode) {
            addProductPlaceholdersList = [];
            addProductPlaceholderMode = 'manual';
            document.getElementById('add-product-form').reset();
            setModalMode(mode);
            var errEl = document.getElementById('add-product-placeholders-json-error');
            if (errEl) errEl.style.display = 'none';
            renderAddProductPlaceholdersManual();
            setAddProductPlaceholderMode('manual');
            var modeProductBtn = document.getElementById('modal-mode-product');
            var modeAddonBtn = document.getElementById('modal-mode-addon');
            if (modeProductBtn && modeAddonBtn) {
                modeProductBtn.classList.toggle('active', mode === 'product');
                modeAddonBtn.classList.toggle('active', mode === 'addon');
            }
            fetch(addonsUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    window.mcsetupsAddonsList = (data.data && data.data.addons) || [];
                    loadAddProductAddons();
                })
                .catch(function() { window.mcsetupsAddonsList = []; loadAddProductAddons(); });
            $('#add-product-modal').modal('show');
        }

        var addProductBtn = document.getElementById('add-product-btn');
        if (addProductBtn) addProductBtn.onclick = function() { openAddModal('addon'); };
        var addAddonBtn = document.getElementById('add-addon-btn');
        if (addAddonBtn) addAddonBtn.onclick = function() { openAddModal('product'); };

        var modeProductBtn = document.getElementById('modal-mode-product');
        var modeAddonBtn = document.getElementById('modal-mode-addon');
        if (modeProductBtn) modeProductBtn.onclick = function() { setModalMode('product'); modeProductBtn.classList.add('active'); if (modeAddonBtn) modeAddonBtn.classList.remove('active'); };
        if (modeAddonBtn) modeAddonBtn.onclick = function() { setModalMode('addon'); modeAddonBtn.classList.add('active'); if (modeProductBtn) modeProductBtn.classList.remove('active'); };

        var phManual = document.getElementById('add-product-ph-mode-manual');
        if (phManual) phManual.onclick = function() {
            if (addProductPlaceholderMode === 'json') {
                try {
                    var raw = document.getElementById('add-product-placeholders-json').value.trim() || '[]';
                    addProductPlaceholdersList = JSON.parse(raw);
                    if (!Array.isArray(addProductPlaceholdersList)) addProductPlaceholdersList = [];
                } catch (e) { addProductPlaceholdersList = []; }
                renderAddProductPlaceholdersManual();
            }
            setAddProductPlaceholderMode('manual');
        };
        var phJson = document.getElementById('add-product-ph-mode-json');
        if (phJson) phJson.onclick = function() { setAddProductPlaceholderMode('json'); };
        var phAdd = document.getElementById('add-product-placeholder-add');
        if (phAdd) phAdd.onclick = function() {
            addProductPlaceholdersList.push({ token: '', label: '', description: '', example: '' });
            renderAddProductPlaceholdersManual();
        };

        var addProductSubmit = document.getElementById('add-product-submit');
        if (addProductSubmit) {
            addProductSubmit.onclick = function() {
                var displayName = document.getElementById('add-product-display-name').value.trim();
                if (!displayName) {
                    alert('Please enter a name.');
                    return;
                }
                var placeholders = [];
                if (addProductPlaceholderMode === 'manual') {
                    placeholders = addProductPlaceholdersList.filter(function(p) { return (p.token || '').trim() || (p.label || '').trim(); }).map(function(p) {
                        return { token: (p.token || '').trim() || undefined, label: (p.label || '').trim() || undefined, description: (p.description || '').trim() || undefined, example: (p.example || '').trim() || undefined };
                    });
                } else {
                    try {
                        var raw = document.getElementById('add-product-placeholders-json').value.trim();
                        placeholders = raw ? JSON.parse(raw) : [];
                        if (!Array.isArray(placeholders)) placeholders = [];
                    } catch (e) {
                        var errEl = document.getElementById('add-product-placeholders-json-error');
                        if (errEl) { errEl.textContent = 'Invalid JSON: ' + (e.message || ''); errEl.style.display = 'block'; }
                        return;
                    }
                }

                var fd = new FormData();
                var url;
                if (useClientUploadApi) {
                    url = modalMode === 'product' ? clientProductsUrl : clientAddonsUrl;
                    fd.append('display_name', displayName);
                    fd.append('placeholders', JSON.stringify(placeholders));
                } else {
                    fd.append('_token', csrf);
                    fd.append('display_name', displayName);
                    fd.append('placeholders', JSON.stringify(placeholders));
                    fd.append('category', document.getElementById('add-product-category').value.trim());
                    fd.append('author_name', document.getElementById('add-product-author-name').value.trim());
                }

                if (modalMode === 'product') {
                    var fileInput = document.getElementById('add-product-file');
                    if (!fileInput || !fileInput.files || !fileInput.files[0]) {
                        alert('Please select an archive file.');
                        return;
                    }
                    var fileFieldName = useClientUploadApi ? 'file' : 'product_file';
                    fd.append(fileFieldName, fileInput.files[0]);
                    fd.append('description', document.getElementById('add-product-description').value.trim());
                    var requiredIds = [];
                    document.querySelectorAll('#add-product-required-addons input:checked').forEach(function(cb) { requiredIds.push(cb.value); });
                    var optionalIds = [];
                    document.querySelectorAll('#add-product-optional-addons input:checked').forEach(function(cb) { optionalIds.push(cb.value); });
                    fd.append('required_addon_ids', JSON.stringify(requiredIds));
                    fd.append('optional_addon_ids', JSON.stringify(optionalIds));
                    fd.append('game_version', document.getElementById('add-product-game-version').value.trim());
                    fd.append('category', document.getElementById('add-product-category').value.trim());
                    fd.append('author_name', document.getElementById('add-product-author-name').value.trim());
                    var coverInput = document.getElementById('add-product-cover-image');
                    if (coverInput.files && coverInput.files[0]) fd.append('cover_image', coverInput.files[0]);
                } else {
                    var addonFileInput = document.getElementById('add-addon-file');
                    if (!addonFileInput || !addonFileInput.files || !addonFileInput.files[0]) {
                        alert('Please select a file.');
                        return;
                    }
                    var fileFieldName = useClientUploadApi ? 'file' : 'addon_file';
                    fd.append(fileFieldName, addonFileInput.files[0]);
                    fd.append('file_type', document.getElementById('add-addon-file-type').value.trim());
                    fd.append('file_location', document.getElementById('add-addon-file-location').value.trim());
                    if (useClientUploadApi) {
                        fd.append('addon_category', document.getElementById('add-product-category').value.trim());
                    }
                    var addonPh = [];
                    if (addProductPlaceholderMode === 'manual') {
                        addonPh = addProductPlaceholdersList.filter(function(p) { return (p.token || '').trim() || (p.label || '').trim(); }).map(function(p) {
                            return { token: (p.token || '').trim() || undefined, label: (p.label || '').trim() || undefined, description: (p.description || '').trim() || undefined, example: (p.example || '').trim() || undefined };
                        });
                    } else {
                        try {
                            var raw = document.getElementById('add-product-placeholders-json').value.trim();
                            addonPh = raw ? JSON.parse(raw) : [];
                            if (!Array.isArray(addonPh)) addonPh = [];
                        } catch (e) { addonPh = []; }
                    }
                    fd.append('placeholders', JSON.stringify(addonPh));
                    var coverInput = document.getElementById('add-product-cover-image');
                    if (coverInput.files && coverInput.files[0]) fd.append('cover_image', coverInput.files[0]);
                }
                if (!useClientUploadApi && modalMode === 'addon') {
                    fd.append('category', document.getElementById('add-product-category').value.trim());
                    fd.append('author_name', document.getElementById('add-product-author-name').value.trim());
                }

                if (!url) url = modalMode === 'product' ? uploadProductUrl : uploadAddonUrl;
                var fileForLog = modalMode === 'product' ? (fileInput && fileInput.files && fileInput.files[0]) : (addonFileInput && addonFileInput.files && addonFileInput.files[0]);
                // #region agent log
                if (fileForLog) {
                    fetch('http://localhost:7448/ingest/17a7d3e0-487b-46ba-9a76-1144235d3a72',{method:'POST',headers:{'Content-Type':'application/json','X-Debug-Session-Id':'918ad3'},body:JSON.stringify({sessionId:'918ad3',location:'index.blade.php:add-product-submit',message:'upload submit',data:{mode:modalMode,name:fileForLog.name,size_kb:Math.round(fileForLog.size/1024),url:url},timestamp:Date.now()})}).catch(function(){});
                }
                // #endregion
                addProductSubmit.disabled = true;
                var uploadTimeout = 300000;
                var uploadAbort = new AbortController();
                var uploadTimer = setTimeout(function() { uploadAbort.abort(); }, uploadTimeout);
                var headers = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf };
                if (useClientUploadApi && mcsetupsLicenseKey) headers['X-License-Key'] = mcsetupsLicenseKey;
                var isClientProduct = useClientUploadApi && modalMode === 'product';
                fetch(url, {
                    method: 'POST',
                    body: fd,
                    credentials: 'same-origin',
                    headers: headers,
                    signal: uploadAbort.signal
                })
                    .then(function(r) {
                        clearTimeout(uploadTimer);
                        var contentType = r.headers.get('Content-Type') || '';
                        if (isClientProduct && r.body) {
                            return r.text().then(function(text) {
                                var last = null;
                                var lines = text.split(/\r?\n/);
                                for (var i = 0; i < lines.length; i++) {
                                    var line = lines[i].trim();
                                    if (!line) continue;
                                    try {
                                        last = JSON.parse(line);
                                        if (last && last.success === true) break;
                                    } catch (e) {}
                                }
                                if (last && typeof last.success !== 'undefined') {
                                    var data = last.data || last;
                                    if (last.success) data.success = true;
                                    return { ok: !!last.success, data: data };
                                }
                                return { ok: false, data: { error: last && last.error ? last.error : (text || 'Invalid response') } };
                            });
                        }
                        if (contentType.indexOf('application/json') !== -1) {
                            return r.json().then(function(data) { return { ok: r.ok, data: data }; });
                        }
                        return r.text().then(function(text) { return { ok: r.ok, data: { error: text || 'Unknown error' } }; });
                    })
                    .then(function(result) {
                        addProductSubmit.disabled = false;
                        if (result.ok && result.data && result.data.success) {
                            $('#add-product-modal').modal('hide');
                            if (modalMode === 'product') loadProducts();
                            else loadAddons();
                            window.location.reload();
                        } else {
                            var errMsg = 'Upload failed.';
                            if (result.data) {
                                if (typeof result.data.error === 'string') errMsg = result.data.error;
                                else if (typeof result.data.message === 'string') errMsg = result.data.message;
                                else if (result.data.errors && typeof result.data.errors === 'object') {
                                    var parts = [];
                                    Object.keys(result.data.errors).forEach(function(k) {
                                        var v = result.data.errors[k];
                                        var s = Array.isArray(v) ? v.map(function(x) { return typeof x === 'string' ? x : String(x); }).join(', ') : String(v);
                                        parts.push(s);
                                    });
                                    errMsg = parts.join(' ');
                                } else if (typeof result.data === 'string') errMsg = result.data;
                                else errMsg = JSON.stringify(result.data);
                            }
                            alert(errMsg);
                        }
                    })
                    .catch(function(err) {
                        clearTimeout(uploadTimer);
                        addProductSubmit.disabled = false;
                        var msg = err && err.name === 'AbortError' ? 'Upload timed out (5 min). Try a smaller file or check your connection.' : (err && typeof err.message === 'string' ? err.message : 'Unknown error');
                        alert('Upload failed: ' + msg);
                    });
            };
        }
    })();
    </script>
@endsection
@endif
