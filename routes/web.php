<?php

use App\Http\Controllers\AcademicYearWebController;
use App\Http\Controllers\AccountingWebController;
use App\Http\Controllers\ActivityLogWebController;
use App\Http\Controllers\AttendanceWebController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CertificateWebController;
use App\Http\Controllers\ClassCouncilWebController;
use App\Http\Controllers\DatabaseBackupWebController;
use App\Http\Controllers\EnrollmentWebController;
use App\Http\Controllers\GradeImportWebController;
use App\Http\Controllers\GradeWebController;
use App\Http\Controllers\LoginHistoryWebController;
use App\Http\Controllers\MockExamWebController;
use App\Http\Controllers\NumberingSettingWebController;
use App\Http\Controllers\PaymentWebController;
use App\Http\Controllers\ProfileWebController;
use App\Http\Controllers\ReportCardWebController;
use App\Http\Controllers\ReportWebController;
use App\Http\Controllers\RequiredStudentDocumentWebController;
use App\Http\Controllers\SchoolClassWebController;
use App\Http\Controllers\SchoolDashboardController;
use App\Http\Controllers\SchoolSettingWebController;
use App\Http\Controllers\StaffRoleWebController;
use App\Http\Controllers\StaffUserWebController;
use App\Http\Controllers\StudentCardWebController;
use App\Http\Controllers\StudentDocumentWebController;
use App\Http\Controllers\StudentImportWebController;
use App\Http\Controllers\StudentWebController;
use App\Http\Controllers\SubjectWebController;
use App\Http\Controllers\TariffWebController;
use App\Http\Controllers\TimetableWebController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AuthController::class, 'showLogin'])->name('login');
Route::get('/login', [AuthController::class, 'showLogin']);
Route::post('/login', [AuthController::class, 'login'])->name('login.store');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::get('/dashboard', SchoolDashboardController::class)
    ->middleware('auth')
    ->name('dashboard');

Route::view('/help', 'help.index')
    ->middleware('auth')
    ->name('help.index');

Route::get('/activity-logs', [ActivityLogWebController::class, 'index'])
    ->middleware(['auth', 'permission:activity_logs.view'])
    ->name('activity-logs.index');

Route::get('/activity-logs/{activityLog}', [ActivityLogWebController::class, 'show'])
    ->middleware(['auth', 'permission:activity_logs.view'])
    ->name('activity-logs.show');

Route::get('/login-histories', LoginHistoryWebController::class)
    ->middleware(['auth', 'permission:activity_logs.view'])
    ->name('login-histories.index');

Route::get('/profile', [ProfileWebController::class, 'show'])
    ->middleware('auth')
    ->name('profile.show');

Route::put('/profile', [ProfileWebController::class, 'update'])
    ->middleware('auth')
    ->name('profile.update');

Route::put('/profile/password', [ProfileWebController::class, 'updatePassword'])
    ->middleware('auth')
    ->name('profile.password.update');

Route::get('/attendance', [AttendanceWebController::class, 'index'])
    ->middleware(['auth', 'permission:attendance.view'])
    ->name('attendance.index');

Route::get('/attendance/pdf', [AttendanceWebController::class, 'pdf'])
    ->middleware(['auth', 'permission:attendance.reports'])
    ->name('attendance.pdf');

Route::get('/attendance/export', [AttendanceWebController::class, 'export'])
    ->middleware(['auth', 'permission:attendance.reports'])
    ->name('attendance.export');

Route::get('/attendance/sessions/{attendanceSession}/pdf', [AttendanceWebController::class, 'sessionPdf'])
    ->middleware(['auth', 'permission:attendance.reports'])
    ->name('attendance.sessions.pdf');

Route::get('/attendance/students/{student}', [AttendanceWebController::class, 'studentHistory'])
    ->middleware(['auth', 'permission:attendance.view'])
    ->name('attendance.students.history');

Route::get('/attendance/students/{student}/pdf', [AttendanceWebController::class, 'studentHistoryPdf'])
    ->middleware(['auth', 'permission:attendance.reports'])
    ->name('attendance.students.history.pdf');

Route::post('/attendance/sessions', [AttendanceWebController::class, 'storeSession'])
    ->middleware(['auth', 'permission:attendance.create'])
    ->name('attendance.sessions.store');

Route::get('/attendance/sessions/{attendanceSession}/edit', [AttendanceWebController::class, 'editSession'])
    ->middleware(['auth', 'permission:attendance.view'])
    ->name('attendance.sessions.edit');

Route::put('/attendance/sessions/{attendanceSession}', [AttendanceWebController::class, 'updateSession'])
    ->middleware(['auth', 'permission:attendance.create'])
    ->name('attendance.sessions.update');

Route::delete('/attendance/records/{attendanceRecord}', [AttendanceWebController::class, 'clearRecord'])
    ->middleware(['auth', 'permission:attendance.update'])
    ->name('attendance.records.clear');

Route::get('/settings', [SchoolSettingWebController::class, 'edit'])
    ->middleware(['auth', 'permission:settings.manage'])
    ->name('settings.edit');

Route::put('/settings', [SchoolSettingWebController::class, 'update'])
    ->middleware(['auth', 'permission:settings.manage'])
    ->name('settings.update');

Route::get('/settings/required-documents', [RequiredStudentDocumentWebController::class, 'index'])
    ->middleware(['auth', 'permission:settings.manage'])
    ->name('settings.required-documents.index');

Route::post('/settings/required-documents', [RequiredStudentDocumentWebController::class, 'store'])
    ->middleware(['auth', 'permission:settings.manage'])
    ->name('settings.required-documents.store');

Route::put('/settings/required-documents/{requiredDocument}', [RequiredStudentDocumentWebController::class, 'update'])
    ->middleware(['auth', 'permission:settings.manage'])
    ->name('settings.required-documents.update');

Route::delete('/settings/required-documents/{requiredDocument}', [RequiredStudentDocumentWebController::class, 'destroy'])
    ->middleware(['auth', 'permission:settings.manage'])
    ->name('settings.required-documents.destroy');

Route::get('/settings/numbering', [NumberingSettingWebController::class, 'index'])
    ->middleware(['auth', 'permission:settings.manage'])
    ->name('settings.numbering.index');

Route::put('/settings/numbering', [NumberingSettingWebController::class, 'update'])
    ->middleware(['auth', 'permission:settings.manage'])
    ->name('settings.numbering.update');

Route::get('/settings/backups', [DatabaseBackupWebController::class, 'index'])
    ->middleware(['auth', 'permission:settings.manage'])
    ->name('settings.backups.index');

Route::post('/settings/backups', [DatabaseBackupWebController::class, 'store'])
    ->middleware(['auth', 'permission:settings.manage'])
    ->name('settings.backups.store');

Route::get('/settings/backups/{filename}', [DatabaseBackupWebController::class, 'download'])
    ->middleware(['auth', 'permission:settings.manage'])
    ->where('filename', '.*')
    ->name('settings.backups.download');

Route::get('/academic-years', [AcademicYearWebController::class, 'index'])
    ->middleware(['auth', 'permission:academic_years.manage'])
    ->name('academic-years.index');

Route::post('/academic-years', [AcademicYearWebController::class, 'store'])
    ->middleware(['auth', 'permission:academic_years.manage'])
    ->name('academic-years.store');

Route::put('/academic-years/{academicYear}', [AcademicYearWebController::class, 'update'])
    ->middleware(['auth', 'permission:academic_years.manage'])
    ->name('academic-years.update');

Route::put('/academic-years/{academicYear}/activate', [AcademicYearWebController::class, 'activate'])
    ->middleware(['auth', 'permission:academic_years.manage'])
    ->name('academic-years.activate');

Route::post('/academic-years/terms', [AcademicYearWebController::class, 'storeTerm'])
    ->middleware(['auth', 'permission:academic_years.manage'])
    ->name('academic-years.terms.store');

Route::put('/academic-years/terms/{term}', [AcademicYearWebController::class, 'updateTerm'])
    ->middleware(['auth', 'permission:academic_years.manage'])
    ->name('academic-years.terms.update');

Route::get('/subjects', [SubjectWebController::class, 'index'])
    ->middleware(['auth', 'permission:settings.manage'])
    ->name('subjects.index');

Route::post('/subjects', [SubjectWebController::class, 'storeSubject'])
    ->middleware(['auth', 'permission:settings.manage'])
    ->name('subjects.store');

Route::put('/subjects/{subject}', [SubjectWebController::class, 'updateSubject'])
    ->middleware(['auth', 'permission:settings.manage'])
    ->name('subjects.update');

Route::post('/subjects/class-subjects', [SubjectWebController::class, 'storeClassSubject'])
    ->middleware(['auth', 'permission:settings.manage'])
    ->name('subjects.class-subjects.store');

Route::put('/subjects/class-subjects/{classSubject}', [SubjectWebController::class, 'updateClassSubject'])
    ->middleware(['auth', 'permission:settings.manage'])
    ->name('subjects.class-subjects.update');

Route::delete('/subjects/class-subjects/{classSubject}', [SubjectWebController::class, 'destroyClassSubject'])
    ->middleware(['auth', 'permission:settings.manage'])
    ->name('subjects.class-subjects.destroy');

Route::post('/subjects/defaults', [SubjectWebController::class, 'applyDefaults'])
    ->middleware(['auth', 'permission:settings.manage'])
    ->name('subjects.defaults');

Route::get('/timetables', [TimetableWebController::class, 'index'])
    ->middleware(['auth', 'permission:timetables.view'])
    ->name('timetables.index');

Route::post('/timetables', [TimetableWebController::class, 'store'])
    ->middleware(['auth', 'permission:timetables.manage'])
    ->name('timetables.store');

Route::post('/timetables/example', [TimetableWebController::class, 'applyExample'])
    ->middleware(['auth', 'permission:timetables.manage'])
    ->name('timetables.example');

Route::get('/timetables/{timetable}/edit', [TimetableWebController::class, 'edit'])
    ->middleware(['auth', 'permission:timetables.manage'])
    ->name('timetables.edit');

Route::put('/timetables/{timetable}', [TimetableWebController::class, 'update'])
    ->middleware(['auth', 'permission:timetables.manage'])
    ->name('timetables.update');

Route::get('/timetables/{timetable}/pdf', [TimetableWebController::class, 'pdf'])
    ->middleware(['auth', 'permission:timetables.print'])
    ->name('timetables.pdf');

Route::get('/grades', [GradeWebController::class, 'index'])
    ->middleware(['auth', 'permission:grades.view'])
    ->name('grades.index');

Route::get('/mock-exams', [MockExamWebController::class, 'index'])
    ->middleware(['auth', 'permission:mock_exams.view'])
    ->name('mock-exams.index');

Route::post('/mock-exams', [MockExamWebController::class, 'store'])
    ->middleware(['auth', 'permission:mock_exams.manage'])
    ->name('mock-exams.store');

Route::post('/mock-exams/{mockExam}/sync-candidates', [MockExamWebController::class, 'syncCandidates'])
    ->middleware(['auth', 'permission:mock_exams.manage'])
    ->name('mock-exams.candidates.sync');

Route::post('/mock-exams/{mockExam}/generate-anonymity', [MockExamWebController::class, 'generateAnonymousCodes'])
    ->middleware(['auth', 'permission:mock_exams.manage'])
    ->name('mock-exams.anonymity.generate');

Route::post('/mock-exams/{mockExam}/distribute-rooms', [MockExamWebController::class, 'distributeRooms'])
    ->middleware(['auth', 'permission:mock_exams.manage'])
    ->name('mock-exams.rooms.distribute');

Route::get('/mock-exams/{mockExam}/candidates/pdf', [MockExamWebController::class, 'candidatesPdf'])
    ->middleware(['auth', 'permission:mock_exams.print'])
    ->name('mock-exams.candidates.pdf');

Route::get('/mock-exams/{mockExam}/rooms/pdf', [MockExamWebController::class, 'roomsPdf'])
    ->middleware(['auth', 'permission:mock_exams.print'])
    ->name('mock-exams.rooms.pdf');

Route::get('/mock-exams/{mockExam}/anonymity/pdf', [MockExamWebController::class, 'anonymityPdf'])
    ->middleware(['auth', 'permission:mock_exams.print'])
    ->name('mock-exams.anonymity.pdf');

Route::post('/grades/assessments', [GradeWebController::class, 'storeAssessment'])
    ->middleware(['auth', 'permission:grades.create'])
    ->name('grades.assessments.store');

Route::get('/grades/assessments/{assessment}/pdf', [GradeWebController::class, 'assessmentPdf'])
    ->middleware(['auth', 'permission:grades.view'])
    ->name('grades.assessments.pdf');

Route::get('/grades/assessments/{assessment}/import', [GradeImportWebController::class, 'create'])
    ->middleware(['auth', 'permission:grades.update'])
    ->name('grades.import');

Route::get('/grades/assessments/{assessment}/import/template', [GradeImportWebController::class, 'template'])
    ->middleware(['auth', 'permission:grades.update'])
    ->name('grades.import.template');

Route::post('/grades/assessments/{assessment}/import/preview', [GradeImportWebController::class, 'preview'])
    ->middleware(['auth', 'permission:grades.update'])
    ->name('grades.import.preview');

Route::post('/grades/assessments/{assessment}/import', [GradeImportWebController::class, 'store'])
    ->middleware(['auth', 'permission:grades.update'])
    ->name('grades.import.store');

Route::delete('/grades/assessments/{assessment}/import', [GradeImportWebController::class, 'destroy'])
    ->middleware(['auth', 'permission:grades.update'])
    ->name('grades.import.destroy');

Route::get('/grades/assessments/{assessment}/export', [GradeWebController::class, 'assessmentExport'])
    ->middleware(['auth', 'permission:grades.view'])
    ->name('grades.assessments.export');

Route::put('/grades/assessments/{assessment}', [GradeWebController::class, 'updateGrades'])
    ->middleware(['auth', 'permission:grades.update'])
    ->name('grades.assessments.grades.update');

Route::put('/grades/assessments/{assessment}/lock', [GradeWebController::class, 'lockAssessment'])
    ->middleware(['auth', 'permission:grades.lock'])
    ->name('grades.assessments.lock');

Route::put('/grades/assessments/{assessment}/unlock', [GradeWebController::class, 'unlockAssessment'])
    ->middleware(['auth', 'permission:grades.unlock'])
    ->name('grades.assessments.unlock');

Route::delete('/grades/assessments/{assessment}', [GradeWebController::class, 'destroyAssessment'])
    ->middleware(['auth', 'permission:grades.update'])
    ->name('grades.assessments.destroy');

Route::get('/report-cards', [ReportCardWebController::class, 'index'])
    ->middleware(['auth', 'permission:report_cards.view'])
    ->name('report-cards.index');

Route::get('/class-council', [ClassCouncilWebController::class, 'index'])
    ->middleware(['auth', 'permission:report_cards.view'])
    ->name('class-council.index');

Route::get('/class-council/pv/pdf', [ClassCouncilWebController::class, 'pvPdf'])
    ->middleware(['auth', 'permission:report_cards.print'])
    ->name('class-council.pv-pdf');

Route::post('/class-council/lock', [ClassCouncilWebController::class, 'lock'])
    ->middleware(['auth', 'permission:report_cards.validate'])
    ->name('class-council.lock');

Route::post('/class-council/unlock', [ClassCouncilWebController::class, 'unlock'])
    ->middleware(['auth', 'permission:grades.unlock'])
    ->name('class-council.unlock');

Route::post('/report-cards/generate', [ReportCardWebController::class, 'generate'])
    ->middleware(['auth', 'permission:report_cards.generate'])
    ->name('report-cards.generate');

Route::put('/report-cards/{reportCard}', [ReportCardWebController::class, 'update'])
    ->middleware(['auth', 'permission:report_cards.validate'])
    ->name('report-cards.update');

Route::get('/report-cards/class/pdf', [ReportCardWebController::class, 'classPdf'])
    ->middleware(['auth', 'permission:report_cards.print'])
    ->name('report-cards.class-pdf');

Route::get('/report-cards/class/export', [ReportCardWebController::class, 'classExport'])
    ->middleware(['auth', 'permission:report_cards.print'])
    ->name('report-cards.class-export');

Route::get('/report-cards/{reportCard}/pdf', [ReportCardWebController::class, 'pdf'])
    ->middleware(['auth', 'permission:report_cards.print'])
    ->name('report-cards.pdf');

Route::get('/report-cards/{reportCard}/transcript/pdf', [ClassCouncilWebController::class, 'transcriptPdf'])
    ->middleware(['auth', 'permission:report_cards.print'])
    ->name('report-cards.transcript-pdf');

Route::get('/accounting/cash-journal', [AccountingWebController::class, 'cashJournal'])
    ->middleware(['auth', 'permission:payments.reports'])
    ->name('accounting.cash-journal');

Route::get('/accounting/cash-journal/pdf', [AccountingWebController::class, 'cashJournalPdf'])
    ->middleware(['auth', 'permission:payments.reports'])
    ->name('accounting.cash-journal.pdf');

Route::get('/accounting/balance-sheet', [AccountingWebController::class, 'balanceSheet'])
    ->middleware(['auth', 'permission:payments.reports'])
    ->name('accounting.balance-sheet');

Route::get('/accounting/balance-sheet/pdf', [AccountingWebController::class, 'balanceSheetPdf'])
    ->middleware(['auth', 'permission:payments.reports'])
    ->name('accounting.balance-sheet.pdf');

Route::get('/accounting/expenses', [AccountingWebController::class, 'expenses'])
    ->middleware(['auth', 'permission:payments.reports'])
    ->name('accounting.expenses.index');

Route::get('/accounting/expenses/create', [AccountingWebController::class, 'createExpense'])
    ->middleware(['auth', 'permission:payments.create'])
    ->name('accounting.expenses.create');

Route::post('/accounting/expenses', [AccountingWebController::class, 'storeExpense'])
    ->middleware(['auth', 'permission:payments.create'])
    ->name('accounting.expenses.store');

Route::get('/accounting/expenses/pdf', [AccountingWebController::class, 'expensesPdf'])
    ->middleware(['auth', 'permission:payments.reports'])
    ->name('accounting.expenses.pdf');

Route::get('/accounting/expenses/{expense}', [AccountingWebController::class, 'showExpense'])
    ->middleware(['auth', 'permission:payments.reports'])
    ->name('accounting.expenses.show');

Route::put('/accounting/expenses/{expense}/cancel', [AccountingWebController::class, 'cancelExpense'])
    ->middleware(['auth', 'permission:payments.cancel'])
    ->name('accounting.expenses.cancel');

Route::get('/reports/class-list', [ReportWebController::class, 'classList'])
    ->middleware(['auth', 'permission:students.export'])
    ->name('reports.class-list');

Route::get('/reports/class-list/pdf', [ReportWebController::class, 'classListPdf'])
    ->middleware(['auth', 'permission:students.export'])
    ->name('reports.class-list.pdf');

Route::get('/reports/class-list/export', [ReportWebController::class, 'classListExport'])
    ->middleware(['auth', 'permission:students.export'])
    ->name('reports.class-list.export');

Route::get('/reports/missing-documents', [ReportWebController::class, 'missingDocuments'])
    ->middleware(['auth', 'permission:students.export'])
    ->name('reports.missing-documents');

Route::get('/reports/missing-documents/pdf', [ReportWebController::class, 'missingDocumentsPdf'])
    ->middleware(['auth', 'permission:students.export'])
    ->name('reports.missing-documents.pdf');

Route::get('/reports/missing-documents/export', [ReportWebController::class, 'missingDocumentsExport'])
    ->middleware(['auth', 'permission:students.export'])
    ->name('reports.missing-documents.export');

Route::get('/reports/incomplete-students', [ReportWebController::class, 'incompleteStudents'])
    ->middleware(['auth', 'permission:students.export'])
    ->name('reports.incomplete-students');

Route::get('/reports/incomplete-students/export', [ReportWebController::class, 'incompleteStudentsExport'])
    ->middleware(['auth', 'permission:students.export'])
    ->name('reports.incomplete-students.export');

Route::get('/reports/payment-situation', [ReportWebController::class, 'paymentSituation'])
    ->middleware(['auth', 'permission:payments.reports'])
    ->name('reports.payment-situation');

Route::get('/reports/payment-situation/pdf', [ReportWebController::class, 'paymentSituationPdf'])
    ->middleware(['auth', 'permission:payments.reports'])
    ->name('reports.payment-situation.pdf');

Route::get('/reports/payment-situation/export', [ReportWebController::class, 'paymentSituationExport'])
    ->middleware(['auth', 'permission:payments.reports'])
    ->name('reports.payment-situation.export');

Route::get('/reports/installments', [ReportWebController::class, 'installmentSituation'])
    ->middleware(['auth', 'permission:payments.reports'])
    ->name('reports.installments');

Route::get('/reports/installments/pdf', [ReportWebController::class, 'installmentSituationPdf'])
    ->middleware(['auth', 'permission:payments.reports'])
    ->name('reports.installments.pdf');

Route::get('/staff/roles', [StaffRoleWebController::class, 'index'])
    ->middleware(['auth', 'permission:roles.manage'])
    ->name('staff.roles.index');

Route::get('/staff/roles/{role}/edit', [StaffRoleWebController::class, 'edit'])
    ->middleware(['auth', 'permission:roles.manage'])
    ->name('staff.roles.edit');

Route::put('/staff/roles/{role}', [StaffRoleWebController::class, 'update'])
    ->middleware(['auth', 'permission:roles.manage'])
    ->name('staff.roles.update');

Route::put('/staff/{user}/reset-password', [StaffUserWebController::class, 'resetPassword'])
    ->middleware(['auth', 'permission:users.manage'])
    ->name('staff.reset-password');

Route::resource('staff', StaffUserWebController::class)
    ->middleware(['auth', 'permission:users.manage'])
    ->parameters(['staff' => 'user']);

Route::get('/students/{student}/registration-sheet', [StudentWebController::class, 'registrationSheet'])
    ->middleware(['auth', 'permission:students.export'])
    ->name('students.registration-sheet');

Route::get('/students/{student}/registration-sheet/pdf', [StudentWebController::class, 'registrationSheetPdf'])
    ->middleware(['auth', 'permission:students.export'])
    ->name('students.registration-sheet.pdf');

Route::post('/students/{student}/documents', [StudentDocumentWebController::class, 'store'])
    ->middleware(['auth', 'permission:students.update'])
    ->name('students.documents.store');

Route::delete('/students/{student}/documents/{studentDocument}', [StudentDocumentWebController::class, 'destroy'])
    ->middleware(['auth', 'permission:students.update'])
    ->name('students.documents.destroy');

Route::get('/student-documents/{studentDocument}', [StudentDocumentWebController::class, 'show'])
    ->middleware(['auth', 'permission:students.view'])
    ->name('student-documents.show');

Route::get('/student-documents/{studentDocument}/download', [StudentDocumentWebController::class, 'download'])
    ->middleware(['auth', 'permission:students.view'])
    ->name('student-documents.download');

Route::post('/classes/{schoolClass}/students', [SchoolClassWebController::class, 'attachStudent'])
    ->middleware(['auth', 'permission:classes.manage'])
    ->name('classes.students.attach');

Route::delete('/classes/{schoolClass}/students/{enrollment}', [SchoolClassWebController::class, 'detachStudent'])
    ->middleware(['auth', 'permission:classes.manage'])
    ->name('classes.students.detach');

Route::get('/classes', [SchoolClassWebController::class, 'index'])
    ->middleware(['auth', 'permission:classes.manage'])
    ->name('classes.index');
Route::get('/classes/create', [SchoolClassWebController::class, 'create'])
    ->middleware(['auth', 'permission:classes.manage'])
    ->name('classes.create');
Route::post('/classes', [SchoolClassWebController::class, 'store'])
    ->middleware(['auth', 'permission:classes.manage'])
    ->name('classes.store');
Route::get('/classes/{schoolClass}', [SchoolClassWebController::class, 'show'])
    ->middleware(['auth', 'permission:classes.manage'])
    ->name('classes.show');
Route::get('/classes/{schoolClass}/edit', [SchoolClassWebController::class, 'edit'])
    ->middleware(['auth', 'permission:classes.manage'])
    ->name('classes.edit');
Route::put('/classes/{schoolClass}', [SchoolClassWebController::class, 'update'])
    ->middleware(['auth', 'permission:classes.manage'])
    ->name('classes.update');
Route::delete('/classes/{schoolClass}', [SchoolClassWebController::class, 'destroy'])
    ->middleware(['auth', 'permission:classes.manage'])
    ->name('classes.destroy');

Route::get('/enrollments', [EnrollmentWebController::class, 'index'])
    ->middleware(['auth', 'permission:enrollments.view'])
    ->name('enrollments.index');
Route::get('/enrollments/create', [EnrollmentWebController::class, 'create'])
    ->middleware(['auth', 'permission:enrollments.create'])
    ->name('enrollments.create');
Route::post('/enrollments', [EnrollmentWebController::class, 'store'])
    ->middleware(['auth', 'permission:enrollments.create'])
    ->name('enrollments.store');
Route::get('/enrollments/{enrollment}', [EnrollmentWebController::class, 'show'])
    ->middleware(['auth', 'permission:enrollments.view'])
    ->name('enrollments.show');
Route::get('/enrollments/{enrollment}/edit', [EnrollmentWebController::class, 'edit'])
    ->middleware(['auth', 'permission:enrollments.update'])
    ->name('enrollments.edit');
Route::put('/enrollments/{enrollment}', [EnrollmentWebController::class, 'update'])
    ->middleware(['auth', 'permission:enrollments.update'])
    ->name('enrollments.update');
Route::delete('/enrollments/{enrollment}', [EnrollmentWebController::class, 'destroy'])
    ->middleware(['auth', 'permission:enrollments.cancel'])
    ->name('enrollments.destroy');

Route::get('/payments/unpaid', [PaymentWebController::class, 'unpaid'])
    ->middleware(['auth', 'permission:payments.reports'])
    ->name('payments.unpaid');

Route::get('/payments/unpaid/export', [PaymentWebController::class, 'unpaidExport'])
    ->middleware(['auth', 'permission:payments.reports'])
    ->name('payments.unpaid.export');

Route::get('/payments/students/{student}/statement', [PaymentWebController::class, 'studentStatement'])
    ->middleware(['auth', 'permission:payments.view'])
    ->name('payments.students.statement');

Route::get('/payments/students/{student}/statement/pdf', [PaymentWebController::class, 'studentStatementPdf'])
    ->middleware(['auth', 'permission:payments.reports'])
    ->name('payments.students.statement.pdf');

Route::get('/payments/{payment}/receipt', [PaymentWebController::class, 'receipt'])
    ->middleware(['auth', 'permission:payments.print_receipt'])
    ->name('payments.receipt');

Route::get('/payments', [PaymentWebController::class, 'index'])
    ->middleware(['auth', 'permission:payments.view'])
    ->name('payments.index');
Route::get('/payments/create', [PaymentWebController::class, 'create'])
    ->middleware(['auth', 'permission:payments.create'])
    ->name('payments.create');

Route::get('/payments/export', [PaymentWebController::class, 'export'])
    ->middleware(['auth', 'permission:payments.reports'])
    ->name('payments.export');

Route::post('/payments', [PaymentWebController::class, 'store'])
    ->middleware(['auth', 'permission:payments.create'])
    ->name('payments.store');
Route::get('/payments/{payment}', [PaymentWebController::class, 'show'])
    ->middleware(['auth', 'permission:payments.view'])
    ->name('payments.show');
Route::delete('/payments/{payment}', [PaymentWebController::class, 'destroy'])
    ->middleware(['auth', 'permission:payments.cancel'])
    ->name('payments.destroy');

Route::get('/tariffs', [TariffWebController::class, 'index'])
    ->middleware(['auth', 'permission:settings.manage'])
    ->name('tariffs.index');

Route::post('/tariffs/defaults', [TariffWebController::class, 'applyDefaults'])
    ->middleware(['auth', 'permission:settings.manage'])
    ->name('tariffs.defaults');

Route::get('/tariffs/classes/{schoolClass}/edit', [TariffWebController::class, 'edit'])
    ->middleware(['auth', 'permission:settings.manage'])
    ->name('tariffs.edit');

Route::put('/tariffs/classes/{schoolClass}', [TariffWebController::class, 'update'])
    ->middleware(['auth', 'permission:settings.manage'])
    ->name('tariffs.update');

Route::get('/certificates/{certificate}/pdf', [CertificateWebController::class, 'pdf'])
    ->middleware(['auth', 'permission:students.export'])
    ->name('certificates.pdf');

Route::get('/certificates', [CertificateWebController::class, 'index'])
    ->middleware(['auth', 'permission:students.export'])
    ->name('certificates.index');
Route::get('/certificates/create', [CertificateWebController::class, 'create'])
    ->middleware(['auth', 'permission:students.export'])
    ->name('certificates.create');
Route::post('/certificates', [CertificateWebController::class, 'store'])
    ->middleware(['auth', 'permission:students.export'])
    ->name('certificates.store');
Route::get('/certificates/{certificate}', [CertificateWebController::class, 'show'])
    ->middleware(['auth', 'permission:students.export'])
    ->name('certificates.show');

Route::get('/students', [StudentWebController::class, 'index'])
    ->middleware(['auth', 'permission:students.view'])
    ->name('students.index');
Route::get('/students/create', [StudentWebController::class, 'create'])
    ->middleware(['auth', 'permission:students.create'])
    ->name('students.create');

Route::get('/students/import', [StudentImportWebController::class, 'create'])
    ->middleware(['auth', 'permission:students.import'])
    ->name('students.import');

Route::get('/students/import/template', [StudentImportWebController::class, 'template'])
    ->middleware(['auth', 'permission:students.import'])
    ->name('students.import.template');

Route::post('/students/import/preview', [StudentImportWebController::class, 'preview'])
    ->middleware(['auth', 'permission:students.import'])
    ->name('students.import.preview');

Route::post('/students/import', [StudentImportWebController::class, 'store'])
    ->middleware(['auth', 'permission:students.import'])
    ->name('students.import.store');

Route::delete('/students/import', [StudentImportWebController::class, 'destroy'])
    ->middleware(['auth', 'permission:students.import'])
    ->name('students.import.destroy');

Route::get('/students/export', [StudentWebController::class, 'export'])
    ->middleware(['auth', 'permission:students.export'])
    ->name('students.export');

Route::get('/students/{student}/school-card/pdf', [StudentCardWebController::class, 'pdf'])
    ->middleware(['auth', 'permission:students.export'])
    ->name('students.school-card.pdf');

Route::post('/students', [StudentWebController::class, 'store'])
    ->middleware(['auth', 'permission:students.create'])
    ->name('students.store');
Route::get('/students/{student}', [StudentWebController::class, 'show'])
    ->middleware(['auth', 'permission:students.view'])
    ->name('students.show');
Route::get('/students/{student}/edit', [StudentWebController::class, 'edit'])
    ->middleware(['auth', 'permission:students.update'])
    ->name('students.edit');
Route::put('/students/{student}', [StudentWebController::class, 'update'])
    ->middleware(['auth', 'permission:students.update'])
    ->name('students.update');
Route::delete('/students/{student}', [StudentWebController::class, 'destroy'])
    ->middleware(['auth', 'permission:students.delete'])
    ->name('students.destroy');
