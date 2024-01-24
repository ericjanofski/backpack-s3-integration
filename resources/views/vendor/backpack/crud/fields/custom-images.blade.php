@php
    use Illuminate\Support\Facades\Storage;

    $field['prefix'] = $field['prefix'] ?? '';
    $field['disk'] = $field['disk'] ?? null;

    $filetypes = isset($field['options']) && isset($field['options']['filetypes']) ? $field['options']['filetypes'] : ["jpg", "jpeg", "png"];

    $fileMimes = [];
    foreach ($filetypes as $item) {
        $fileMimes[] = "image/$item";
    }

    $fileMimeErrorMessage = "<strong>Required file types: " . implode(', ', $filetypes) . "</strong>.";

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

    // if value isn't a base 64 image, generate URL
    if($value && !preg_match('/^data\:image\//', $value)) {
        // make sure to prepend the prefix once to value
        $imageUrl = Str::start($value, Str::finish($field['prefix'], '/'));

        // generate URL
        $imageUrl = getDiskUrl($field, $imageUrl);
    }

    $max_image_size_in_megabytes = env('IMAGE_UPLOAD_MAX_FILESIZE', 20);

    $field['wrapper'] = $field['wrapper'] ?? $field['wrapperAttributes'] ?? [];
    $field['wrapper']['class'] = $field['wrapper']['class'] ?? "form-group col-sm-12";
    $field['wrapper']['class'] = $field['wrapper']['class'].' cropperImage';
    $field['wrapper']['data-aspectRatio'] = $field['aspect_ratio'] ?? 0;
    $field['wrapper']['data-crop'] = $field['crop'] ?? false;
    $field['wrapper']['data-field-name'] = $field['wrapper']['data-field-name'] ?? $field['name'];
    $field['wrapper']['data-init-function'] = $field['wrapper']['data-init-function'] ?? 'bpFieldInitCropperImageElement';
@endphp

@include('crud::fields.inc.wrapper_start')

    <label>{!! $field['label'] !!}</label>
    @include('crud::fields.inc.translatable_icon')

    {{-- Wrap the image or canvas element with a block element (container) --}}
    <div class="row">
        <div class="col-sm-6" data-handle="previewArea" style="margin-bottom: 20px;">
            <img data-handle="mainImage" src="">
        </div>
        <div class="loading col-12" style="margin-bottom:20px;margin-left:20px;display:none;">
            <img src="/storage/loading.gif" style="width:30px;height:30px;" />
        </div>

        <div style="display:none;" data-handle="removedImage" data-value=""></div>
    </div>
    <div class="btn-group">
        <div class="btn btn-light btn-sm btn-file">
            {{ trans('backpack::crud.choose_file') }} <input type="file" accept="image/*" data-handle="uploadImage"  @include('crud::fields.inc.attributes')>
            <input type="hidden" data-handle="hiddenImage" name="{{ $field['name'] }}" data-value-prefix="{{ $field['prefix'] }}" data-value-url="{{ $imageUrl ?? '' }}" value="{{ $value }}">
        </div>
    </div>
    <button class="btn btn-light btn-sm" data-handle="remove" type="button"><i class="la la-trash"></i></button>

    {{-- HINT --}}
    @if (isset($field['hint']))
        <p class="help-block">{!! $field['hint'] !!}</p>
    @endif


@include('crud::fields.inc.wrapper_end')


{{-- ########################################## --}}
{{-- Extra CSS and JS for this particular field --}}
{{-- If a field type is shown multiple times on a form, the CSS and JS will only be loaded once --}}

{{-- FIELD CSS - will be loaded in the after_styles section --}}
@push('crud_fields_styles')
    <style>
        .image .btn-group {
            margin-top: 10px;
        }
        img {
            max-width: 100%; /* This rule is very important, please do not ignore this! */
        }
        .img-container, .img-preview {
            width: 100%;
            text-align: center;
        }
        .img-preview {
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

{{-- FIELD JS - will be loaded in the after_scripts section --}}
@push('crud_fields_scripts')
    <script>
        function bpFieldInitCropperImageElement(element) {
                // Find DOM elements under this form-group element
                var $mainImage = element.find('[data-handle=mainImage]');
                var $uploadImage = element.find("[data-handle=uploadImage]");
                var $hiddenImage = element.find("[data-handle=hiddenImage]");
                var $remove = element.find("[data-handle=remove]");
                var $removedImage = element.find("[data-handle=removedImage]");
                var $previews = element.find("[data-handle=previewArea]");
                var $loading = $(".loading");
                var $fileMimes = <?php echo json_encode($fileMimes); ?>
                // Options either global for all image type fields, or use 'data-*' elements for options passed in via the CRUD controller
                var options = {
                    viewMode: 2,
                    checkOrientation: false,
                    autoCropArea: 1,
                    responsive: true,
                    preview : element.find('.img-preview'),
                    aspectRatio : element.attr('data-aspectRatio')
                };

            // Hide 'Remove' button if there is no image saved
            if (!$hiddenImage.val()){
                $previews.hide();
                $remove.hide();
            }
            // Make the main image show the image in the hidden input url (image loaded from database) or show the preview data
            $mainImage.attr('src', $hiddenImage.data('value-url').length > 0 ? $hiddenImage.data('value-url') : $hiddenImage.val());

            $remove.click(function() {
                $removedImage.attr('data-value', new URL($mainImage.attr('src')).pathname.slice(1))
                $mainImage.attr('src','');
                $hiddenImage.val('');
                $remove.hide();
                $previews.hide();
            });

            $uploadImage.change(function() {
                $loading.show();

                var fileReader = new FileReader(),
                        files = this.files,
                        file;

                if (!files.length) {
                    $loading.hide();
                    return;
                }
                file = files[0];

                const maxImageSize = {{ $max_image_size_in_megabytes }};
                if(maxImageSize > 0 && file.size / 1000000 > maxImageSize) {
                    new Noty({
                        type: "error",
                        text: 'Please pick an image smaller than '+maxImageSize+'M.'
                    }).show();
                    $loading.hide();
                } else if($fileMimes.includes(file.type)) {
                    fileReader.readAsDataURL(file);
                    fileReader.onload = function () {
                        const formData = new FormData();
                        formData.append('image', file)
                        if($mainImage.attr('src').length != 0) {
                            $removedImage.attr('data-value', new URL($mainImage.attr('src')).pathname.slice(1))
                            $mainImage.attr('src','');
                        }

                        const config = {
                            headers: {
                                "ContentType": "multipart/form-data"
                            }
                        }
                        axios.post('/custom-media/image/new', formData, config)
                        .then((res)=>{
                            if(res.data.success) {
                                $uploadImage.val("");
                                $previews.show();
                                let fullUrl = "<?php echo $bucketPath; ?>" + '/' + res.data.url
                                $mainImage.attr('src', fullUrl);
                                $hiddenImage.val(res.data.url);
                                $remove.show();
                                $loading.hide();

                                // delete removed image
                                if($removedImage.attr('data-value').length > 0) {
                                    axios.post('/custom-media/delete', {'url': $removedImage.attr('data-value')})
                                    .then((res)=>{
                                        if(res.data.success) {
                                            $removedImage.attr('data-value', '')
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
                        })
                    };
                } else {

                    new Noty({
                        type: "error",
                        text: "<?php echo $fileMimeErrorMessage; ?>"
                    }).show();
                    $loading.hide();
                }
                $uploadImage.val("");
            });

            element.on('CrudField:disable', function(e) {
                element.children('.btn-group').children('button[data-handle=remove]').attr('disabled','disabled');
                element.children('.btn-group').children('.btn-file').children('input[data-handle=uploadImage]').attr('disabled','disabled');
            });

            element.on('CrudField:enable', function(e) {
                element.children('.btn-group').children('button[data-handle=remove]').removeAttr('disabled');
                element.children('.btn-group').children('.btn-file').children('input[data-handle=uploadImage]').removeAttr('disabled');
            });
    }
    </script>
@endpush

{{-- End of Extra CSS and JS --}}
{{-- ########################################## --}}
