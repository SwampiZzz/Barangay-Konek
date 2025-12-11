<?php
// Government service landing page – polished and commercial-ready
$isLoggedIn = !empty($_SESSION['user_id']);
$dashboardNav = current_user_role() === ROLE_USER
	? 'user-dashboard'
	: (current_user_role() === ROLE_STAFF
		? 'staff-dashboard'
		: (current_user_role() === ROLE_ADMIN ? 'admin-dashboard' : 'superadmin-dashboard'));
?>

<section class="home-hero">
	<div class="container container-narrow">
		<div class="row align-items-center g-4">
		<div class="col-lg-7">
			<img src="<?php echo WEB_ROOT; ?>/public/assets/img/Barangay-Konek-Logo-with-Letter.png" alt="Barangay Konek" class="hero-badge-logo">
			<h1 class="display-5 fw-bold text-white mt-3" style="text-transform: uppercase; letter-spacing: 1px;">Barangay Konek</h1>
			<p class="lead text-white-70">Connecting communities, empowering citizens. Your services, simplified.</p>
				<div class="d-flex flex-wrap align-items-center gap-3 mt-4">
					<?php if (!$isLoggedIn): ?>
						<button class="btn btn-lg btn-light" data-bs-toggle="modal" data-bs-target="#registerModal">Create an account</button>
						<button class="btn btn-lg btn-outline-light" data-bs-toggle="modal" data-bs-target="#loginModal">Sign in</button>
					<?php else: ?>
						<a class="btn btn-lg btn-light" href="index.php?nav=<?php echo $dashboardNav; ?>">Go to dashboard</a>
						<a class="btn btn-lg btn-outline-light" href="index.php?nav=create-request">Create a request</a>
					<?php endif; ?>
				</div>
			</div>
			<div class="col-lg-5">
				<div class="hero-panel">
					<div class="action-list">
						<div class="action-item">
							<div>
								<h6 class="mb-1" style="color: black;">Request a document</h6>
								<p class="text-muted small mb-0">Upload requirements and monitor approvals.</p>
							</div>
						</div>
						<div class="action-item">
							<div>
								<h6 class="mb-1" style="color: black;" >Submit a complaint</h6>
								<p class="text-muted small mb-0">Escalate issues with transparent status updates.</p>
							</div>
						</div>
						<div class="action-item">
							<div>
								<h6 class="mb-1" style="color: black;" >Read official advisories</h6>
								<p class="text-muted small mb-0">Stay updated on announcements and schedules.</p>
							</div>
						</div>
					</div>
					<div class="mt-3 d-flex align-items-center text-muted small">
						<i class="fas fa-lock me-2"></i>Encrypted traffic • Session protection • Audit trails
					</div>
				</div>
			</div>
		</div>
	</div>
</section>

<section class="section section--alt">
	<div class="container container-narrow">
		<div class="row align-items-center mb-4">
			<div class="col-lg-7">
				<h2 class="h3 mb-2 section-title">Built for public service</h2>
				<p class="text-muted mb-0 section-subtitle">Clear, accountable workflows for residents, frontline staff, and administrators.</p>
			</div>
			<div class="col-lg-5 text-lg-end mt-3 mt-lg-0">
				<span class="pill pill-soft me-2">Audit-friendly</span>
				<span class="pill pill-soft me-2">Soft-delete safety</span>
				<span class="pill pill-outline">Role-aware</span>
			</div>
		</div>
		<div class="row g-3">
			<div class="col-md-4">
				<div class="service-card">
					<div class="service-icon primary"><i class="fas fa-file-alt"></i></div>
					<h4>Document services</h4>
					<p class="text-muted">Request certificates, submit requirements, and track sign-offs without lining up.</p>
				</div>
			</div>
			<div class="col-md-4">
				<div class="service-card">
					<div class="service-icon warning"><i class="fas fa-exclamation-circle"></i></div>
					<h4>Complaints & feedback</h4>
					<p class="text-muted">Log issues, get acknowledgments, and follow resolution progress transparently.</p>
				</div>
			</div>
			<div class="col-md-4">
				<div class="service-card">
					<div class="service-icon success"><i class="fas fa-bullhorn"></i></div>
					<h4>Announcements</h4>
					<p class="text-muted">Receive official advisories, schedules, and notices from your barangay.</p>
				</div>
			</div>
		</div>
	</div>
</section>

<section class="section section--gradient">
	<div class="container container-narrow">
		<div class="row mb-4">
			<div class="col-lg-7">
				<h3 class="h4 mb-1 section-title">Why Barangay Konek</h3>
				<p class="text-muted mb-0 section-subtitle">Designed to feel modern and commercial, while meeting government-grade standards.</p>
			</div>
		</div>
		<div class="row g-3">
			<div class="col-md-4">
				<div class="guarantee-card">
					<div class="icon-circle"><i class="fas fa-shield-alt"></i></div>
					<h5>Security-first</h5>
					<p class="text-muted small mb-0">Role-based access, session protection, and activity logs by default.</p>
				</div>
			</div>
			<div class="col-md-4">
				<div class="guarantee-card">
					<div class="icon-circle"><i class="fas fa-tasks"></i></div>
					<h5>Operational clarity</h5>
					<p class="text-muted small mb-0">Every request and complaint is traceable, timestamped, and auditable.</p>
				</div>
			</div>
			<div class="col-md-4">
				<div class="guarantee-card">
					<div class="icon-circle"><i class="fas fa-mobile-alt"></i></div>
					<h5>Mobile-ready</h5>
					<p class="text-muted small mb-0">Responsive layouts optimized for residents on phones and staff on desktops.</p>
				</div>
			</div>
		</div>
	</div>
</section>

<section class="section">
	<div class="container container-narrow">
		<div class="cta-panel">
			<div>
				<p class="text-uppercase text-muted small mb-1 section-tag">Get started</p>
				<h3 class="h4 mb-2 section-title">Bring your barangay services online—securely and professionally.</h3>
				<p class="text-muted mb-0 section-subtitle">Launch digital requests, handle complaints, and publish advisories in one place.</p>
			</div>
			<div class="d-flex flex-wrap gap-2 mt-3 mt-lg-0">
				<?php if (!$isLoggedIn): ?>
					<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#registerModal">Create an account</button>
					<button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#loginModal">Sign in</button>
				<?php else: ?>
					<a class="btn btn-primary" href="index.php?nav=<?php echo $dashboardNav; ?>">Open dashboard</a>
					<a class="btn btn-outline-primary" href="index.php?nav=create-request">New request</a>
				<?php endif; ?>
			</div>
		</div>
	</div>
</section>

<div class="container container-narrow mb-5">
	<p class="home-footer-note text-center small">Need assistance or applying for your barangay? Contact <strong>itzmecj7@gmail.com</strong></p>
</div>
