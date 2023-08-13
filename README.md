### S3Sync Gravity Form Addon

## Purpose
  This Gravity Forms addon facilitates direct file uploads to Amazon S3, eliminating the need for intermediate server storage.

## Description
  I have expanded the capabilities of Gravity Forms through the implementation of an addon framework. Within this framework, when users select the upload field option in Gravity Forms, they gain access to an array of advanced tabs. These tabs facilitate the seamless integration of Amazon S3 credentials into the form submission process.
  Notably, two checkboxes are provided within these tabs, offering users the option either to enable or disable S3 uploads and to enable or disable local server storage of the uploaded files. Upon form submission, any files included in the upload field are automatically directed to Amazon S3 for storage. Simultaneously, the corresponding file URLs are meticulously recorded and stored within the entry metadata.
  This solution optimizes the handling of uploads within Gravity Forms, leveraging the power of Amazon S3 while affording users the flexibility to store files on the server if preferred. The implementation streamlines the form submission process and ensures the security and accessibility of uploaded files.


## Process 
1. **Creating a Gravity Form:** Begin by crafting a Gravity Form tailored to your needs.
2. **Incorporating the Upload Field:** Within the form editor, integrate the designated upload field to facilitate file submissions.
3. **Configuring Upload Field Settings:** Click once on the upload field to unveil a comprehensive array of options.
4. **Accessing Advanced Configuration:** Navigate to the "Advanced" tab located on the right-hand side of the interface, alongside other pertinent tabs such as "Appearance."
5. **Customizing Advanced Options:** Within the "Advanced" tab, discover a series of input fields and checkboxes, each offering a distinct set of functionalities.
6. **Integrating Amazon S3 Credentials:** Employ the provided input fields and checkboxes to seamlessly input the necessary Amazon S3 credentials and fine-tune settings according to your specific requirements.
7. **Saving the Form Configuration:** Once satisfied with the adjustments, save the configured form, effectively rendering it ready for deployment on your front-end pages.
8. **Effortless Front-End Utilization:** Upon submission of a form featuring an upload field on the front-end interface, the files included are promptly and directly stored within your Amazon S3 repository.
9. **Streamlined Access to Uploaded Files:** For easy retrieval of uploaded files, navigate to the form entries section. There, locate the dedicated "S3 URL" section positioned conveniently at the bottom of the page.
