<line_removed:42>onMounted(() => {
<line_removed:43>  // Redirigir si ya hay sesión activa
<line_removed:44>  const adminToken = localStorage.getItem('n3xt_admin_token')
<line_removed:45>  const customerToken = localStorage.getItem('n3xt_customer_token')
<line_removed:46>  
<line_removed:47>  if (adminToken) {
<line_removed:48>    router.push('/admin')
<line_removed:49>    return
<line_removed:50>  } else if (customerToken) {
<line_removed:51>    router.push('/customer/dashboard')
<line_removed:52>    return
<line_removed:53>  }
<line_removed:54>
<line_removed:55>  const savedEmail = localStorage.getItem('n3xt_remember_email')
<line_removed:56>  if (savedEmail) {
<line_removed:57>    email.value = savedEmail
<line_removed:58>    rememberMe.value = true
<line_removed:59>  }
<line_removed:60>})
<line_added:42>onMounted(() => {
<line_added:43>  // Redirigir si ya hay sesión activa (Solo Admin para evitar bucles)
<line_added:44>  const adminToken = localStorage.getItem('n3xt_admin_token')
<line_added:45>  
<line_added:46>  if (adminToken) {
<line_added:47>    router.push('/admin')
<line_added:48>    return
<line_added:49>  }
<line_added:50>  
<line_added:51>  // Eliminamos la redirección automática de clientes para permitir el cambio de cuenta al admin
<line_added:52>
<line_added:53>  const savedEmail = localStorage.getItem('n3xt_remember_email')
<line_added:54>  if (savedEmail) {
<line_added:55>    email.value = savedEmail
<line_added:56>    rememberMe.value = true
<line_added:57>  }
<line_added:58>})