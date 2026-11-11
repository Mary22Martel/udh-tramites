import '../css/app.css';
import { createRoot } from 'react-dom/client';
import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import { AuthProvider, homeFor, useAuth } from './context/AuthContext';
import { ThemeProvider } from './context/ThemeContext';
import { ToastProvider } from './context/ToastContext';
import Layout from './components/Layout';
import { Spinner } from './components/ui';
import Login from './pages/Login';
import Profile from './pages/Profile';
import Catalog from './pages/Catalog';
import StudentHome from './pages/student/StudentHome';
import NewSubmission from './pages/student/NewSubmission';
import MySubmissions from './pages/student/MySubmissions';
import MyPayments from './pages/student/MyPayments';
import CajaPayments from './pages/CajaPayments';
import Payments from './pages/staff/Payments';
import StaffHome from './pages/staff/StaffHome';
import Inbox from './pages/staff/Inbox';
import AdminDashboard from './pages/admin/AdminDashboard';
import AdminSubmissions from './pages/admin/AdminSubmissions';
import Procedures from './pages/admin/Procedures';
import ProcedureEditor from './pages/admin/ProcedureEditor';
import Organization from './pages/admin/Organization';
import People from './pages/admin/People';
import DataLoad from './pages/admin/DataLoad';
import Reports from './pages/admin/Reports';

function Protected({ roles, children }) {
    const { user, loading } = useAuth();
    if (loading) return <Spinner className="min-h-screen" />;
    if (!user) return <Navigate to="/login" replace />;
    if (roles && !roles.includes(user.role) && !(roles.includes('staff') && user.role !== 'ESTUDIANTE')) {
        return <Navigate to={homeFor(user)} replace />;
    }
    return children;
}

function Home() {
    const { user, loading } = useAuth();
    if (loading) return <Spinner className="min-h-screen" />;
    return <Navigate to={homeFor(user)} replace />;
}

function App() {
    return (
        <Routes>
            <Route path="/login" element={<Login />} />
            <Route path="/" element={<Home />} />
            <Route element={<Protected><Layout /></Protected>}>
                {/* Estudiante */}
                <Route path="/inicio" element={<Protected roles={['ESTUDIANTE']}><StudentHome /></Protected>} />
                <Route path="/catalogo/:id/solicitar" element={<Protected roles={['ESTUDIANTE']}><NewSubmission /></Protected>} />
                <Route path="/mis-tramites" element={<Protected roles={['ESTUDIANTE']}><MySubmissions /></Protected>} />
                <Route path="/mis-pagos" element={<Protected roles={['ESTUDIANTE']}><MyPayments /></Protected>} />
                <Route path="/caja" element={<Protected roles={['ESTUDIANTE', 'TESORERIA', 'ADMINISTRADOR']}><CajaPayments /></Protected>} />
                <Route path="/pagos" element={<Protected roles={['TESORERIA', 'ADMINISTRADOR']}><Payments /></Protected>} />
                {/* Personal de áreas */}
                <Route path="/panel" element={<Protected roles={['staff']}><StaffHome /></Protected>} />
                <Route path="/bandeja" element={<Protected roles={['staff']}><Inbox scope="pendientes" /></Protected>} />
                <Route path="/atendidos" element={<Protected roles={['staff']}><Inbox scope="atendidos" /></Protected>} />
                {/* Administrador */}
                <Route path="/admin" element={<Protected roles={['ADMINISTRADOR']}><AdminDashboard /></Protected>} />
                <Route path="/admin/reportes" element={<Protected roles={['ADMINISTRADOR']}><Reports /></Protected>} />
                <Route path="/admin/solicitudes" element={<Protected roles={['ADMINISTRADOR']}><AdminSubmissions /></Protected>} />
                <Route path="/admin/tramites" element={<Protected roles={['ADMINISTRADOR']}><Procedures /></Protected>} />
                <Route path="/admin/tramites/nuevo" element={<Protected roles={['ADMINISTRADOR']}><ProcedureEditor /></Protected>} />
                <Route path="/admin/tramites/:id" element={<Protected roles={['ADMINISTRADOR']}><ProcedureEditor /></Protected>} />
                <Route path="/admin/organizacion" element={<Protected roles={['ADMINISTRADOR']}><Organization /></Protected>} />
                <Route path="/admin/usuarios" element={<Protected roles={['ADMINISTRADOR']}><People /></Protected>} />
                <Route path="/admin/carga" element={<Protected roles={['ADMINISTRADOR']}><DataLoad /></Protected>} />
                {/* Comunes */}
                <Route path="/catalogo" element={<Catalog />} />
                <Route path="/perfil" element={<Profile />} />
            </Route>
            <Route path="*" element={<Home />} />
        </Routes>
    );
}

createRoot(document.getElementById('app')).render(
    <ThemeProvider>
        <ToastProvider>
            <BrowserRouter>
                <AuthProvider>
                    <App />
                </AuthProvider>
            </BrowserRouter>
        </ToastProvider>
    </ThemeProvider>,
);
