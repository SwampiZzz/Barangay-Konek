<?php
// Modern, government-inspired landing page (Philippines style)
?>

<section class="section hero">
	<div class="hero-image" style="background-image: url('<?php echo WEB_ROOT; ?>/public/assets/img/front-photo.jpg');">
		<div class="hero-overlay" style="padding: 5.5rem 0;">
			<div class="container container-narrow text-white">
				<div class="row align-items-center">
					<div class="col-lg-7">
						<div class="mb-3 d-flex align-items-center">
							<img src="<?php echo WEB_ROOT; ?>/public/assets/img/Barangay-Konek-Logo-with-Letter.png" alt="Barangay Konek" style="height:78px;" class="me-3">
						</div>
						<h1 class="display-5 fw-bold">Barangay Konek</h1>
						<p class="lead">Request documents, submit complaints, and stay informed — with the transparency expected of local government services.</p>
						<div class="mt-4">
							<?php if (empty($_SESSION['user_id'])): ?>
								<button class="btn btn-primary btn-lg me-3" data-bs-toggle="modal" data-bs-target="#registerModal">Get Started</button>
								<button class="btn btn-outline-light btn-lg" data-bs-toggle="modal" data-bs-target="#loginModal">Sign In</button>
							<?php else: ?>
								<a href="index.php?nav=<?php echo current_user_role() === ROLE_USER ? 'dashboard' : (current_user_role() === ROLE_STAFF ? 'staff-dashboard' : (current_user_role() === ROLE_ADMIN ? 'admin-dashboard' : 'superadmin-dashboard')); ?>" class="btn btn-primary btn-lg me-3">Go to Dashboard</a>
								<a href="index.php?nav=create-request" class="btn btn-outline-light btn-lg">Create Request</a>
							<?php endif; ?>
						</div>
						<div class="d-flex flex-wrap text-white-50 small mt-3">
							<span class="me-3"><i class="fas fa-check-circle me-1"></i> Secure identity verification</span>
							<span class="me-3"><i class="fas fa-check-circle me-1"></i> Soft-delete & audit logs</span>
							<span><i class="fas fa-check-circle me-1"></i> Role-based access</span>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</section>

<section class="section section--alt">
	<div class="container container-narrow">
		<div class="row mb-4">
			<div class="col-lg-8">
				<h2 class="h3 mb-2">What you can do</h2>
				<p class="text-muted mb-0">Tailored for residents, staff, admins, and super admins to keep services efficient, transparent, and accountable.</p>
			</div>
		</div>

		<div class="row g-3">
			<div class="col-md-4">
				<div class="feature-card h-100" style="border-left:4px solid var(--primary-color);">
					<div class="d-flex">
						<div class="icon-wrap me-3" style="background:var(--primary-color); color:#fff;"><i class="fas fa-file-alt fa-lg"></i></div>
						<div>
							<h4 class="text-dark">Document Requests</h4>
							<p class="text-muted mb-0">Request barangay certificates, upload requirements, and track approvals.</p>
						</div>
					</div>
				</div>
			</div>
			<div class="col-md-4">
				<div class="feature-card h-100" style="border-left:4px solid var(--warning-color);">
					<div class="d-flex">
						<div class="icon-wrap me-3" style="background:var(--warning-color); color:#1f1f1f;"><i class="fas fa-exclamation-circle fa-lg"></i></div>
						<div>
							<h4 class="text-dark">Complaints & Feedback</h4>
							<p class="text-muted mb-0">Submit issues (even anonymously) and receive updates as staff/admins process them.</p>
						</div>
					</div>
				</div>
			</div>
			<div class="col-md-4">
				<div class="feature-card h-100" style="border-left:4px solid var(--success-color);">
					<div class="d-flex">
						<div class="icon-wrap me-3" style="background:var(--success-color); color:#fff;"><i class="fas fa-bullhorn fa-lg"></i></div>
						<div>
							<h4 class="text-dark">Announcements</h4>
							<p class="text-muted mb-0">Read official barangay announcements, advisories, and schedules.</p>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</section>

<section class="section">
	<div class="container container-narrow">
		<div class="row align-items-center mb-4">
			<div class="col-lg-8">
				<h3 class="h4 mb-1">How it works</h3>
				<p class="text-muted mb-0">A clear flow for residents and staff, with checks and approvals built in.</p>
			</div>
		</div>
		<div class="row g-3">
			<div class="col-md-4">
				<div class="step-card" style="border-top:4px solid var(--primary-color);">
					<div class="icon mb-2" style="background:var(--primary-color); color:#fff; width:48px; height:48px; line-height:48px; border-radius:8px; display:inline-block;"><i class="fas fa-user-check"></i></div>
					<h5 class="mt-3">Register & Verify</h5>
					<p class="text-muted small mb-0">Create an account, submit verification, and get approved by admins.</p>
				</div>
			</div>
			<div class="col-md-4">
				<div class="step-card" style="border-top:4px solid var(--warning-color);">
					<div class="icon mb-2" style="background:var(--warning-color); color:#1f1f1f; width:48px; height:48px; line-height:48px; border-radius:8px; display:inline-block;"><i class="fas fa-file-signature"></i></div>
					<h5 class="mt-3">Submit & Track</h5>
					<p class="text-muted small mb-0">File document requests or complaints and follow status updates in real time.</p>
				</div>
			</div>
			<div class="col-md-4">
				<div class="step-card" style="border-top:4px solid var(--success-color);">
					<div class="icon mb-2" style="background:var(--success-color); color:#fff; width:48px; height:48px; line-height:48px; border-radius:8px; display:inline-block;"><i class="fas fa-hands-helping"></i></div>
					<h5 class="mt-3">Process & Deliver</h5>
					<p class="text-muted small mb-0">Staff and admins process, approve, and deliver; audit logs keep the trail.</p>
				</div>
			</div>
		</div>
	</div>
</section>

<section class="section section--alt">
	<div class="container container-narrow">
		<div class="row align-items-center">
			<div class="col-lg-8">
				<h3 class="h4 mb-1">Ready to connect with your barangay?</h3>
				<p class="text-muted mb-0">Start your request or file a report today. Official, transparent, and secure.</p>
			</div>
			<div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
				<?php if (empty($_SESSION['user_id'])): ?>
					<button class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#registerModal">Create an Account</button>
				<?php else: ?>
					<a class="btn btn-primary btn-lg" href="index.php?nav=create-request">Create a Request</a>
				<?php endif; ?>
			</div>
		</div>
	</div>
</section>

<div class="container container-narrow mb-5">
	<p class="home-footer-note text-center small">Need assistance? Contact <strong>devs@barangaykonek.local</strong></p>
</div>
