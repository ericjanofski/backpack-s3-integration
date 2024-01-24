@php
    $field['wrapper'] = $field['wrapper'] ?? $field['wrapperAttributes'] ?? [];
    $field['wrapper']['data-init-function'] = $field['wrapper']['data-init-function'] ?? 'bpFieldInitCustomS3VideoElement';
    $field['wrapper']['data-field-name'] = $field['wrapper']['data-field-name'] ?? $field['name'];

    $max_video_size_in_megabytes = isset($field['maxFileSize']) ? $field['maxFileSize'] : env('VIDEO_UPLOAD_MAX_FILESIZE', 150);

    $field['acceptedMimeTypes'] = isset($field['acceptedMimeTypes']) ? $field['acceptedMimeTypes'] : ["video/mp4","video/m4v","video/mov"];

    $bucketPath = env('AWS_BUCKET_PATH');

    $value = old_empty_or_null($field['name'], '') ?? $field['value'] ?? $field['default'] ?? '';

    if (! function_exists('getDiskUrl')) {
        function getDiskUrl($field, $value) {
            try {
                return Storage::url($value);
            }
            catch (Exception $e) {
                // the driver does not support retrieving URLs (eg. SFTP)
                Log::error($e);
            }
        }
    }

    if($value) {
        $mediaUrl = getDiskUrl($field, $value);
    }

@endphp

{{-- text input --}}
@include('crud::fields.inc.wrapper_start')
    <label>{!! $field['label'] !!}</label>
    @include('crud::fields.inc.translatable_icon')

    <div class="row">
        <div class="col-sm-6" data-handle="previewArea" style="margin-bottom: 10px;">
            <video controls width="250" data-handle="videoPlayer">
                <source data-handle="mainMedia" src="" />
            </video>
        </div>
        <div class="loading col-12" style="margin-bottom:20px;margin-left:20px;display:none;">
            <img src="/storage/loading.gif" style="width:30px;height:30px;" />
            <div style="padding-top: 10px; padding-bottom:10px;">Please wait until the video has uploaded, then click "Save".</div>
        </div>

        <div style="display:none;" data-handle="removedMedia" data-value=""></div>
    </div>
    <div class="btn-group" data-handle="chooseButton">
        <div class="btn btn-light btn-md btn-file">
            {{ trans('backpack::crud.choose_file') }}
            <input type="file" accept="video/*" data-handle="uploadMedia"  @include('crud::fields.inc.attributes')>
        </div>
    </div>

    <button class="btn btn-light btn-lg" data-handle="remove" type="button"><i class="la la-trash"></i></button>

    <input type="hidden" data-handle="hiddenMedia" name="{{ $field['name'] }}" data-value-url="{{
    $mediaUrl ?? '' }}" value="{{ $value }}">

    {{-- HINT --}}
    @if (isset($field['hint']))
        <p class="help-block" style="padding-top:10px;">{!! $field['hint'] !!}</p>
    @endif
@include('crud::fields.inc.wrapper_end')



{{-- ########################################## --}}
{{-- Extra CSS and JS for this particular field --}}
{{-- If a field type is shown multiple times on a form, the CSS and JS will only be loaded once --}}

@push('crud_fields_styles')
<style>
    .media .btn-group {
        margin-top: 10px;
    }

    .media-container, .media-preview {
        width: 100%;
        text-align: center;
    }
    .media-preview {
        float: left;
        margin-right: 10px;
        margin-bottom: 10px;
        overflow: hidden;
    }
    .preview-lg {
        width: 263px;
        height: 148px;
    }

    .btn-file {
        position: relative;
        overflow: hidden;
    }
    .btn-file input[type=file] {
        position: absolute;
        top: 0;
        right: 0;
        min-width: 100%;
        min-height: 100%;
        font-size: 100px;
        text-align: right;
        filter: alpha(opacity=0);
        opacity: 0;
        outline: none;
        background: white;
        cursor: inherit;
        display: block;
    }
</style>
@endpush

@push('crud_fields_scripts')
    <script>
        function bpFieldInitCustomS3VideoElement(element) {
            var $mainMedia = element.find('[data-handle=mainMedia]');
            var $uploadMedia = element.find("[data-handle=uploadMedia]");
            var $hiddenMedia = element.find("[data-handle=hiddenMedia]");
            var $chooseButton = element.find("[data-handle=chooseButton]");
            var $remove = element.find("[data-handle=remove]");
            var $removedMedia = element.find("[data-handle=removedMedia]");
            var $previews = element.find("[data-handle=previewArea]");
            var $loading = $(".loading");
            var $video = element.find('[data-handle=videoPlayer]');

            // Hide 'Remove' button if no Media
            if (!$hiddenMedia.val()){
                $previews.hide();
                $remove.hide();
            }

            // Make the main Media show the Media in the hidden input url (Media loaded from database) or show the preview data
            if($hiddenMedia.data('value-url').length > 0) {
                $mainMedia.attr('src', $hiddenMedia.data('value-url'))
                $video.get(0).load()
                $chooseButton.hide()
            } else {
                $mainMedia.attr('src', $hiddenMedia.val())
            }

            $remove.click(function() {
                $removedMedia.attr('data-value', new URL($mainMedia.attr('src')).pathname.slice(1))
                $mainMedia.attr('src','');
                $hiddenMedia.val('');
                $remove.hide();
                $previews.hide();
                $chooseButton.show()
            });

            $uploadMedia.change(function() {
                $loading.show();
                $chooseButton.hide()

                var fileReader = new FileReader(),
                        files = this.files,
                        file;

                if (!files.length) {
                    $loading.hide();
                    return;
                }
                file = files[0];

                const maxMediaSize = {{ $max_video_size_in_megabytes }};
                const acceptedMimeTypes = JSON.parse('<?php echo json_encode($field['acceptedMimeTypes']); ?>')

                if(maxMediaSize > 0 && file.size / 1000000 > maxMediaSize) {
                    new Noty({
                        type: "error",
                        text: 'Please upload a file smaller than '+maxMediaSize+'M.'
                    }).show();
                    $loading.hide();
                } else if(acceptedMimeTypes && acceptedMimeTypes.includes(file.type)) {
                    fileReader.readAsDataURL(file);
                    fileReader.onload = function () {
                        const formData = new FormData();
                        formData.append('media', file)
                        formData.append('acceptedMimeTypes', JSON.stringify(acceptedMimeTypes))
                        const config = {
                            headers: {
                                "ContentType": "multipart/form-data"
                            }
                        }
                        axios.post('/custom-media/media/new', formData, config)
                        .then((res)=>{
                            if(res.data.success) {
                                $uploadMedia.val("");
                                $previews.show();
                                let fullUrl = "<?php echo $bucketPath; ?>" + '/' + res.data.url
                                $mainMedia.attr('src', fullUrl);
                                $video.get(0).load()

                                $hiddenMedia.val(res.data.url);
                                $remove.show();
                                $loading.hide();
                                $chooseButton.hide()


                                // delete removed Media
                                if($removedMedia.attr('data-value').length > 0) {
                                    axios.post('/custom-media/delete', {'url': $removedMedia.attr('data-value')})
                                    .then((res)=>{
                                        //console.log(res);
                                        if(res.data.success) {
                                            $removedMedia.attr('data-value', '')
                                        } else {
                                            //console.log(res);
                                        }
                                    }).catch(function(error){
                                        //console.log(error);
                                    })
                                }

                            // false status from php uploader
                            } else {
                                new Noty({
                                    type: "error",
                                    text: res.data.message
                                }).show();
                                $loading.hide();
                            }
                        }).catch(function(error){
                            $loading.hide();
                            if(error.response.status == 413) {
                                new Noty({
                                    type: "error",
                                    text: 'You are attempting to upload a file larger than the server permits. Please upload a file smaller than '+maxMediaSize+'M.'
                                }).show();
                            }
                        })
                    };
                } else {
                    new Noty({
                        type: "error",
                        text: "<strong>Please upload an accepted file type.</strong>."
                    }).show();
                    $loading.hide();
                }
                $uploadMedia.val("");
            });

            element.on('CrudField:disable', function(e) {
                element.children('.btn-group').children('button[data-handle=remove]').attr('disabled','disabled');
                element.children('.btn-group').children('.btn-file').children('input[data-handle=uploadMedia]').attr('disabled','disabled');
            });

            element.on('CrudField:enable', function(e) {
                element.children('.btn-group').children('button[data-handle=remove]').removeAttr('disabled');
                element.children('.btn-group').children('.btn-file').children('input[data-handle=uploadMedia]').removeAttr('disabled');
            });
    }
    </script>
@endpush
