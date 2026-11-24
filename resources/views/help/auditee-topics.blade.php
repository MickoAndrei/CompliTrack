            <section id="prepare" data-help-topic="Prepare for Audit" x-show="selected === 'prepare'" aria-labelledby="prepare-title">
                <span class="help-kicker">GET READY</span><h2 id="prepare-title">How to prepare for an audit</h2>
                <p class="help-intro">Start with the request, then prepare the records you are permitted to present.</p>
                <ol class="help-steps">
                    <li><h3>Review the audit notice</h3><p>Read the audit notice and <a href="/auditee/evidence-requests">evidence requests addressed to you</a>. Check the audit scope and what is requested.</p></li>
                    <li><h3>Locate the requested records</h3><p>Locate permitted digital references and authorized records. Follow the owner’s permissions and document-control rules.</p></li>
                    <li><h3>Arrange physical originals</h3><p>Make physical originals available for inspection when required.</p></li>
                    <li><h3>Present evidence</h3><p>Present evidence to the assigned auditor and track review feedback in <a href="/auditee/evidence-requests">My Evidence Requests</a>.</p></li>
                    <li><h3>Follow up on findings</h3><p>Review <a href="/auditee/my-findings">issued audit results</a> and respond to required corrective actions.</p></li>
                </ol>
                <a class="help-primary-link" href="/auditee/evidence-requests">Open My Evidence Requests <span aria-hidden="true">→</span></a>
            </section>
            <section id="present" data-help-topic="Present Evidence" x-show="selected === 'present'" aria-labelledby="present-title">
                <span class="help-kicker">DURING THE AUDIT</span><h2 id="present-title">Present evidence without unnecessary copying</h2>
                <p class="help-intro">Use the permitted method for the request. A file is not mandatory.</p>
                <div class="help-disclosures">
                    <details open><summary>Digital references <span>Allowed codes, locations, or descriptions</span></summary><div><p>Provide an allowed code, location, or factual description. Omit restricted titles and identifiers.</p></div></details>
                    <details><summary>Physical originals <span>Availability and inspection arrangements</span></summary><div><p>Record whether the physical original is available or inspection needs arranging. The auditor records whether any required signature was actually verified.</p><blockquote>“The signed original is available for inspection at the scheduled visit.”</blockquote><p>This illustrative availability statement is not a compliant decision.</p></div></details>
                    <details><summary>Authorized file uploads <span>Only when copying and storage are permitted</span></summary><div><p>Only copy a document if the owner and document-control rules permit storage here. Supported formats are PDF, DOCX, PNG, JPG, maximum 20 MB; files are encrypted privately.</p><p class="help-note"><strong>Protect controlled content.</strong> Never upload protected QMS materials solely because a request exists.</p></div></details>
                </div>
                <p class="help-note">During-audit evidence responds to a specific request from your assigned auditor. Corrective evidence responds to an open nonconforming check/CAR.</p>
                <a class="help-primary-link" href="/auditee/evidence-requests">Respond to an evidence request <span aria-hidden="true">→</span></a>
            </section>
            <section id="respond" data-help-topic="Respond to Findings" x-show="selected === 'respond'" aria-labelledby="respond-title">
                <span class="help-kicker">FOLLOW THROUGH</span><h2 id="respond-title">Respond to findings and corrective actions</h2>
                <p class="help-intro">During-audit evidence and corrective evidence are separate.</p>
                <ol class="help-steps">
                    <li><h3>Review the issued result</h3><p>Open <a href="/auditee/my-findings">My Audit Results</a> and review the finding and any corrective action request (CAR).</p></li>
                    <li><h3>Use the appropriate response</h3><p>During Audit responds to a specific request from your assigned auditor. Corrective Evidence responds to an open nonconforming check/CAR. Open <a href="/auditee/my-findings?actionable=1">actionable findings</a> to review required corrective actions.</p></li>
                    <li><h3>Save and submit evidence</h3><p>For an evidence request, save a response as a draft to resume later. Once submitted, wait for review or a request for additional information. Follow document-control restrictions for any corrective documentation too.</p></li>
                    <li><h3>Monitor review feedback</h3><p>Neither submitting evidence nor uploading a file changes N/C/O or closes a CAR. The auditor reviews the evidence.</p></li>
                </ol>
                <p class="help-note">Your <a href="/auditee/notifications">notification history</a> remains available offline through the application when you return; closed-app OS push is not supported.</p>
                <a class="help-primary-link" href="/auditee/my-findings?actionable=1">Review actionable findings <span aria-hidden="true">→</span></a>
            </section>
            <section id="nco" data-help-topic="Understanding N/C/O" x-show="selected === 'nco'" aria-labelledby="nco-title">
                <span class="help-kicker">READ YOUR RESULTS</span><h2 id="nco-title">Understanding N / C / O</h2>
                <p class="help-intro">The auditor makes these decisions from reviewed evidence.</p>
                <div class="help-decisions">
                    @foreach([['N', 'Non-Conformance', 'A requirement was not met.', 'non_compliant'], ['C', 'Compliant', 'The requirement was met.', 'compliant'], ['O', 'Observation', 'A factual observation recorded by the auditor.', 'observation']] as [$code, $label, $description, $status])
                    <article class="help-decision"><x-status-badge :status="$status" :label="$code"/><h3>{{ $label }}</h3><p>{{ $description }}</p></article>
                    @endforeach
                </div>
                <div class="help-pending"><x-status-badge status="pending" label="Pending"/><div><h3>Awaiting an answer</h3><p>Pending means unanswered, not a compliance result.</p></div></div>
                <p class="help-note">These are process assessments, not employee performance ratings. A finding and a CAR are separate from a checklist decision.</p>
                <a class="help-primary-link" href="/auditee/my-findings">View My Audit Results <span aria-hidden="true">→</span></a>
            </section>
            <section id="standards" data-help-topic="Reference Standards" x-show="selected === 'standards'" aria-labelledby="standards-title">
                <span class="help-kicker">REFERENCE &amp; DOCUMENT CONTROL</span><h2 id="standards-title">Reference standards</h2>
                <p class="help-intro">CompliTrack retains its existing ISO 9001:2015 internal-audit and ARTA reference identifiers. Ask the audit coordinator which approved criteria apply to your process.</p>
                <div class="help-reference"><i data-lucide="book-open" aria-hidden="true"></i><div><h3>Use the approved criteria for your audit</h3><p>Refer to the identifiers in your <a href="/auditee/evidence-requests">evidence requests</a> and <a href="/auditee/my-findings">audit results</a>.</p></div></div>
                <p class="help-note">No institutional retention period, filename rule, or classification scheme is imposed here. Approved external reference links and controlled-form reproduction require confirmation from the institution.</p>
                <div class="help-resource-empty"><h3>No approved external resources listed</h3><p>Ask your audit coordinator for institution-approved links and permission to reproduce controlled forms.</p></div>
            </section>