<?php
/**
 * MediaConverter Pro - Contact Us Page Template
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sent_message = false;
$error_message = false;

if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['mc_contact_nonce'] ) ) {
	if ( wp_verify_nonce( sanitize_text_field( $_POST['mc_contact_nonce'] ), 'mc_contact_action' ) ) {
		$name    = sanitize_text_field( $_POST['mc_name'] ?? '' );
		$email   = sanitize_email( $_POST['mc_email'] ?? '' );
		$subject = sanitize_text_field( $_POST['mc_subject'] ?? 'PrismConvert Support' );
		$message = sanitize_textarea_field( $_POST['mc_message'] ?? '' );

		if ( ! empty( $name ) && is_email( $email ) && ! empty( $message ) ) {
			// In production, sends via wp_mail to admin email.
			$admin_email = get_option( 'admin_email' );
			@wp_mail( $admin_email, "[PrismConvert] {$subject}", "Name: {$name}\nEmail: {$email}\n\nMessage:\n{$message}" );
			$sent_message = true;
		} else {
			$error_message = 'Please fill out all required fields with a valid email address.';
		}
	} else {
		$error_message = 'Security check failed. Please refresh the page.';
	}
}
?>
<div class="mc-wrapper mc-theme-auto">
	<div class="mc-container mc-page-container">
		<div class="mc-page-header">
			<span class="mc-page-badge">Support & Inquiries</span>
			<h1 class="mc-page-title">Contact Support</h1>
			<p class="mc-page-subtitle">Have technical questions, bug reports, or legal inquiries? Our team is here to assist.</p>
		</div>

		<div class="mc-contact-grid">
			<div class="mc-contact-form-card">
				<?php if ( $sent_message ) : ?>
					<div class="mc-alert mc-alert-success">
						<strong>Thank you!</strong> Your message has been received. Our support team will respond shortly.
					</div>
				<?php else : ?>
					<?php if ( $error_message ) : ?>
						<div class="mc-alert mc-alert-danger"><?php echo esc_html( $error_message ); ?></div>
					<?php endif; ?>

					<form method="post" action="">
						<?php wp_nonce_field( 'mc_contact_action', 'mc_contact_nonce' ); ?>
						<div class="mc-form-group">
							<label for="mc_name">Full Name</label>
							<input type="text" id="mc_name" name="mc_name" class="mc-input-field" required />
						</div>
						<div class="mc-form-group">
							<label for="mc_email">Email Address</label>
							<input type="email" id="mc_email" name="mc_email" class="mc-input-field" required />
						</div>
						<div class="mc-form-group">
							<label for="mc_subject">Subject</label>
							<input type="text" id="mc_subject" name="mc_subject" class="mc-input-field" placeholder="Bug report, feature suggestion, DMCA notice..." required />
						</div>
						<div class="mc-form-group">
							<label for="mc_message">Message</label>
							<textarea id="mc_message" name="mc_message" rows="5" class="mc-input-field" required></textarea>
						</div>
						<button type="submit" class="mc-btn-primary mc-btn-large">
							<span>Send Message</span>
						</button>
					</form>
				<?php endif; ?>
			</div>

			<div class="mc-contact-info-card">
				<h3>Technical Assistance</h3>
				<p>We monitor server health, transcoding clusters, and conversion stability around the clock.</p>
				<div class="mc-contact-info-item">
					<strong>Operating Hours:</strong>
					<span>24/7 Automated Processing &bull; Support: Mon-Fri 09:00 - 18:00 UTC</span>
				</div>
				<div class="mc-contact-info-item">
					<strong>Security & DMCA:</strong>
					<span>legal@prismconvert.io</span>
				</div>
				<div class="mc-contact-info-item">
					<strong>Technical Response SLA:</strong>
					<span>Typically within 24 hours</span>
				</div>
			</div>
		</div>
	</div>
</div>
