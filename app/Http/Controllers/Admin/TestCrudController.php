protected function setupCreateOperation()
{
        
  $this->crud->field('image')
      ->type('custom_image')
      ->label('Image')
      ->validationRules('required')
      ->options([
          "filetypes" => ["png"]
      ])
  ->hint('Image must be a png image.');
  
  
  
  $this->crud->field('video')
      ->type('custom_s3_video')
      ->label('Video')
      ->validationRules('required')
      ->acceptedMimeTypes([
          'video/mp4',
          'video/m4v',
      ])
      ->maxFileSize(1000)
      ->hint('Requires mp4 or m4v files under 1G.');
    
}
