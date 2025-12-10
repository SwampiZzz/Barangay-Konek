<?php
// Redesigned home page using sections and full-bleed hero.
?>

<section class="section hero">
	<div class="hero-image" style="background-image: url('<?php echo WEB_ROOT; ?>/public/assets/img/front-photo.jpg');">
		<div class="hero-overlay" style="padding: 6rem 0;">
			<div class="container container-narrow text-white">
				<div class="d-flex align-items-center justify-content-between mb-4">
					<div class="d-flex align-items-center">
						<img src="<?php echo WEB_ROOT; ?>/public/assets/img/Barangay-Konek-Logo-with-Letter.png" alt="Barangay Konek" style="height:100px;" class="d-none d-md-inline">
					</div>
				</div>

				<div class="row align-items-center">
					<div class="col-lg-7">
						<h1 class="display-5 fw-bold">Barangay Konek</h1>
						<p class="lead">Securely request barangay documents, submit complaints, and stay informed with announcements — all in one place.</p>
						<div class="mt-4">
							<?php if (empty($_SESSION['user_id'])): ?>
								<button class="btn btn-primary btn-lg me-3" data-bs-toggle="modal" data-bs-target="#registerModal">Get Started</button>
								<button class="btn btn-outline-light btn-lg" data-bs-toggle="modal" data-bs-target="#loginModal">Sign In</button>
							<?php else: ?>
								<a href="index.php?nav=dashboard" class="btn btn-primary btn-lg me-3">Go to Dashboard</a>
								<a href="index.php?nav=create-request" class="btn btn-outline-light btn-lg">Create Request</a>
							<?php endif; ?>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</section>

<section class="section section--alt">
	<div class="container container-narrow">
		<div class="row align-items-center mb-4">
			<div class="col-md-8">
				<h2 class="h3">What you can do</h2>
				<p class="text-muted">Designed for residents, staff, and administrators to manage requests, complaints, announcements and more — with clear audit trails and verification.</p>
			</div>
		</div>

		<div class="row g-3">
			<div class="col-md-4">
				<div class="feature-card" style="border-left:4px solid var(--primary-color);">
					<div class="d-flex">
						<div class="me-3"><i class="fas fa-file-alt fa-lg"></i></div>
						<div>
							<h4 class="text-dark">Document Requests</h4>
							<p class="text-muted mb-0">Submit requests for certificates, upload required documents, and track progress through staff and admin review.</p>
						</div>
					</div>
				</div>
			</div>
			<div class="col-md-4">
				<div class="feature-card" style="border-left:4px solid var(--warning-color);">
					<div class="d-flex">
						<div class="me-3"><i class="fas fa-exclamation-circle fa-lg"></i></div>
						<div>
							<h4 class="text-dark">Complaints & Feedback</h4>
							<p class="text-muted mb-0">Report issues or provide feedback to barangay staff. Option to submit anonymously when needed.</p>
						</div>
					</div>
				</div>
			</div>
			<div class="col-md-4">
				<div class="feature-card" style="border-left:4px solid var(--success-color);">
					<div class="d-flex">
						<div class="me-3"><i class="fas fa-bullhorn fa-lg"></i></div>
						<div>
							<h4 class="text-dark">Announcements</h4>
							<p class="text-muted mb-0">View public announcements, urgent advisories, and community updates from barangay leadership.</p>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</section>

<section class="section">
	<div class="container container-narrow">
		<h3 class="h4 mb-3">How it works</h3>
		<div class="row g-3">
			<div class="col-md-4">
				<div class="step-card" style="border-top:4px solid var(--primary-color);">
					<div class="icon mb-2" style=" width:48px; height:48px; line-height:48px; border-radius:8px; display:inline-block;"><i class="fas fa-user-check"></i></div>
					<h5 class="mt-3">Register & Verify</h5>
					<p class="text-muted small mb-0">Create an account and submit verification documents for identity confirmation.</p>
				</div>
			</div>
			<div class="col-md-4">
				<div class="step-card" style="border-top:4px solid var(--warning-color);">
					<div class="icon mb-2" style="color:#e3b23c; width:48px; height:48px; line-height:48px; border-radius:8px; display:inline-block;"><i class="fas fa-file-signature"></i></div>
					<h5 class="mt-3">Submit Request</h5>
					<p class="text-muted small mb-0">Provide details and attachments for your document request or complaint.</p>
				</div>
			</div>
			<div class="col-md-4">
				<div class="step-card" style="border-top:4px solid var(--success-color);">
					<div class="icon mb-2" style="color:#1f8a5f; width:48px; height:48px; line-height:48px; border-radius:8px; display:inline-block;"><i class="fas fa-hands-helping"></i></div>
					<h5 class="mt-3">Process & Notify</h5>
					<p class="text-muted small mb-0">Staff and admins process requests; you receive notifications at each stage.</p>
				</div>
			</div>
		</div>
	</div>
</section>

<section class="section section--alt">
	<div class="container container-narrow">
		<div class="row align-items-center">
			<div class="col-md-8">
				<h3 class="h4">Ready to get started?</h3>
				<p class="text-muted">Create your account and request documents now — the barangay is one click away.</p>
			</div>
			<div class="col-md-4 text-md-end mt-3 mt-md-0">
				<?php if (empty($_SESSION['user_id'])): ?>
					<button class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#registerModal">Register Now</button>
				<?php else: ?>
					<a class="btn btn-primary btn-lg" href="index.php?nav=create-request">Create Request</a>
				<?php endif; ?>
			</div>
		</div>
	</div>
</section>

<div class="container container-narrow mb-5">
	<p class="home-footer-note text-center small">Questions? Contact <strong>devs@barangaykonek.local</strong> — support available for barangay admins and maintainers.</p>
</div>
